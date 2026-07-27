import { Injectable, signal } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { tokenStore } from '../api/token-store';

const AUTH_KEY = 'authed';

/**
 * Session state for the customer app. OTP-first, phone-primary (doc 02): the user proves a phone
 * number, no password. Today this is fixture-driven so the onboarding flow is complete and demoable
 * offline; the two seams (`requestOtp` → POST /v1/auth/otp/request, `verifyOtp` → /verify) are where
 * the generated client drops in, returning the same shapes. `ensureReady()` lets the route guard
 * wait for the persisted flag before deciding, so an authed user is never bounced to Welcome on boot.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  readonly authed = signal(false);
  private readonly ready: Promise<void>;
  private pendingPhone = '';

  constructor() {
    this.ready = this.load();
  }

  async ensureReady(): Promise<void> {
    await this.ready;
  }

  get phone(): string {
    return this.pendingPhone;
  }

  /** Start the OTP challenge for an E.164 phone (the server texts the code). */
  async requestOtp(phoneE164: string): Promise<void> {
    this.pendingPhone = phoneE164;
    // seam → POST /v1/auth/otp/request { phone }
  }

  /** Verify the code. Fixture: any 6 digits succeed; the real endpoint decides. */
  async verifyOtp(code: string): Promise<boolean> {
    if (!/^\d{6}$/.test(code)) {
      return false;
    }
    // seam → POST /v1/auth/otp/verify { phone, code } → tokens
    this.authed.set(true);
    await Preferences.set({ key: AUTH_KEY, value: '1' });
    return true;
  }

  async logout(): Promise<void> {
    this.authed.set(false);
    this.pendingPhone = '';
    tokenStore.set(null); // drop the bearer so no stale token rides the next request
    await Preferences.set({ key: AUTH_KEY, value: '0' });
  }

  private async load(): Promise<void> {
    const stored = (await Preferences.get({ key: AUTH_KEY })).value;
    this.authed.set(stored === '1');
  }
}
