import { Injectable, inject, signal } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { api } from '../api/client';
import { tokenStore } from '../api/token-store';
import { OfflineCache } from './offline/offline-cache.service';
import { WriteQueue } from './offline/write-queue.service';
import { RealtimeService } from './realtime.service';

const AUTH_KEY = 'authed';
const TOKEN_KEY = 'access_token';
const REFRESH_KEY = 'refresh_token';

/**
 * Session state for the customer app. OTP-first, phone-primary (doc 02): the user proves a phone
 * number, no password. `requestOtp`/`verifyOtp` call the real API (P1-02/03) with an offline fixture
 * fallback; a successful verify stores the Sanctum access token, which is also PERSISTED so it
 * survives an app reload (else the persisted `authed` flag would leave a token-less session that
 * silently falls back to fixtures). `load()` rehydrates it into the client on boot. `ensureReady()`
 * lets the route guard wait for that before deciding, so an authed user is never bounced to Welcome.
 * (Refresh-token rotation on a 401 — POST /auth/refresh — is the next hardening step; the access
 * token here lives ~15 min.)
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly realtime = inject(RealtimeService);
  private readonly offline = inject(OfflineCache);
  private readonly queue = inject(WriteQueue);

  readonly authed = signal(false);
  private readonly ready: Promise<void>;
  private pendingPhone = '';
  /** One in-flight refresh shared by every 401 that races, so the rotating token isn't reused. */
  private refreshing: Promise<string | null> | null = null;

  constructor() {
    // Register synchronously so the client can rotate the token before load() resolves.
    tokenStore.setRefreshHandler(() => this.refreshAccessToken());
    this.ready = this.load();
  }

  async ensureReady(): Promise<void> {
    await this.ready;
  }

  get phone(): string {
    return this.pendingPhone;
  }

  /** Start the OTP challenge for an E.164 phone — the server texts (or, in dev, logs) the code. */
  async requestOtp(phoneE164: string): Promise<void> {
    this.pendingPhone = phoneE164;
    try {
      await api.POST('/auth/otp/request', {
        body: { phone_e164: phoneE164, purpose: 'login' },
        params: { header: { 'Idempotency-Key': crypto.randomUUID() } },
      });
    } catch {
      // Backend unreachable — the offline fixture demo still proceeds to the verify screen.
    }
  }

  /**
   * Verify the code (P1-02/03). A reachable backend decides: success stores the real Bearer access
   * token; a rejected code returns false (no fallback — that would mask a real failure). Only a
   * NETWORK error falls back to the fixture path (any 6 digits, no token) so the offline demo works.
   */
  async verifyOtp(code: string): Promise<boolean> {
    if (!/^\d{6}$/.test(code)) {
      return false;
    }
    try {
      const { data, error } = await api.POST('/auth/otp/verify', {
        body: { phone_e164: this.pendingPhone, code, purpose: 'login' },
        params: { header: { 'Idempotency-Key': crypto.randomUUID() } },
      });
      if (error !== undefined || data === undefined) {
        return false; // reachable backend rejected the code (wrong/expired) — a real failure
      }
      await this.storeTokens(data.tokens.access_token, data.tokens.refresh_token);
      await this.markAuthed();
      return true;
    } catch {
      // Network error (backend unreachable) → fixture fallback keeps the offline demo working.
      await this.markAuthed();
      return true;
    }
  }

  private async markAuthed(): Promise<void> {
    this.authed.set(true);
    await Preferences.set({ key: AUTH_KEY, value: '1' });
  }

  /**
   * Rotate the access token on a 401 (P1-03). Deduped so racing 401s share one refresh — the backend
   * detects refresh-token reuse and revokes the family, so the latest token must be used exactly once.
   * A failed refresh logs out (the session is truly gone).
   */
  private refreshAccessToken(): Promise<string | null> {
    this.refreshing ??= (async () => {
      try {
        const refresh = (await Preferences.get({ key: REFRESH_KEY })).value;
        if (refresh === null) {
          return null;
        }
        const { data, error } = await api.POST('/auth/refresh', {
          body: { refresh_token: refresh },
          params: { header: { 'Idempotency-Key': crypto.randomUUID() } },
        });
        if (error !== undefined || data === undefined) {
          await this.logout();
          return null;
        }
        await this.storeTokens(data.access_token, data.refresh_token);
        return data.access_token;
      } catch {
        return null; // network error — keep the session, let the caller see the 401
      } finally {
        this.refreshing = null;
      }
    })();
    return this.refreshing;
  }

  private async storeTokens(accessToken: string, refreshToken: string): Promise<void> {
    tokenStore.set(accessToken);
    await Preferences.set({ key: TOKEN_KEY, value: accessToken });
    await Preferences.set({ key: REFRESH_KEY, value: refreshToken });
  }

  async logout(): Promise<void> {
    this.authed.set(false);
    this.pendingPhone = '';
    tokenStore.set(null); // drop the bearer so no stale token rides the next request

    // Everything the offline layer holds belongs to the session that just ended (P5-02). The cache
    // is this user's jobs, addresses and conversations — it must not survive into the next login on
    // a shared phone. The outbox goes too, and that is a deliberate trade: those writes would be
    // replayed with the NEXT session's Bearer, attributing one person's actions to another, which
    // is far worse than losing an unsent message.
    await this.offline.clear();
    await this.queue.clear();
    // Close the socket too: it was authorized with the token we just dropped, so leaving it open
    // would keep streaming this user's threads into the next session.
    this.realtime.disconnect();
    await Preferences.remove({ key: TOKEN_KEY });
    await Preferences.remove({ key: REFRESH_KEY });
    await Preferences.set({ key: AUTH_KEY, value: '0' });
  }

  private async load(): Promise<void> {
    const stored = (await Preferences.get({ key: AUTH_KEY })).value;
    this.authed.set(stored === '1');
    const token = (await Preferences.get({ key: TOKEN_KEY })).value;
    if (token !== null) {
      tokenStore.set(token); // rehydrate the Bearer so authenticated calls work after a reload
    }
  }
}
