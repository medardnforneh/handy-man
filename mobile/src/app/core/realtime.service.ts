import { Injectable } from '@angular/core';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { environment } from '../../environments/environment';
import { tokenStore } from '../api/token-store';

/** The live message payload, shaped exactly like a fetched one (see MessagePosted::broadcastWith). */
export interface LiveMessage {
  id: string;
  conversation_id: string;
  sender_user_id: string | null;
  kind: string;
  body: string | null;
  payload: Record<string, unknown> | null;
  contact_flag: string | null;
  reply_to_id: string | null;
  created_at: string;
}

/** Drop a subscription. Safe to call twice. */
export type Unsubscribe = () => void;

/**
 * The realtime rail (build plan P4-03/04). Wraps Laravel Echo over Reverb so screens subscribe to a
 * channel and get a callback, without knowing about sockets.
 *
 * Reverb is NOT the source of truth (CLAUDE.md stack table). A dropped or missed frame must never
 * corrupt a thread, so callers keep REST authoritative: fetch, then subscribe, and refetch on
 * reconnect (P4-07). Everything here degrades to silence — an unreachable Reverb leaves the app
 * fully usable on REST alone, which is why `connect()` never throws.
 */
@Injectable({ providedIn: 'root' })
export class RealtimeService {
  private echo: Echo<'reverb'> | null = null;
  /** True once a connection attempt failed, so we stop retrying on every subscribe. */
  private unavailable = false;

  /**
   * Lazily build the Echo client. Returns null when there's no session (nothing to authorize with)
   * or the transport can't be constructed — the caller then simply runs without live updates.
   */
  private connect(): Echo<'reverb'> | null {
    if (this.echo !== null) {
      return this.echo;
    }
    if (this.unavailable || tokenStore.get() === null) {
      return null;
    }

    try {
      // Echo looks for a global Pusher; provide it rather than relying on a bundler shim.
      (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

      this.echo = new Echo({
        broadcaster: 'reverb',
        key: environment.reverb.key,
        wsHost: environment.reverb.host,
        wsPort: environment.reverb.port,
        wssPort: environment.reverb.port,
        forceTLS: environment.reverb.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // Bearer clients can't use Laravel's session-based /broadcasting/auth (P4-03), so Echo
        // posts to the sanctum-guarded endpoint and attaches the access token itself.
        authEndpoint: `${environment.apiBaseUrl}/broadcasting/auth`,
        auth: {
          headers: {
            Authorization: `Bearer ${tokenStore.get() ?? ''}`,
            Accept: 'application/json',
          },
        },
      });

      return this.echo;
    } catch {
      this.unavailable = true;
      return null;
    }
  }

  /**
   * Listen for new messages on an engagement's private channel. Returns an unsubscribe function —
   * or a no-op when realtime isn't available, so callers never need to branch.
   */
  onEngagementMessage(engagementId: string, handler: (message: LiveMessage) => void): Unsubscribe {
    const echo = this.connect();
    if (echo === null) {
      return () => undefined;
    }

    const name = `engagement.${engagementId}`;
    try {
      echo.private(name).listen('.message.posted', (payload: LiveMessage) => handler(payload));
    } catch {
      return () => undefined;
    }

    return () => {
      try {
        echo.leave(name);
      } catch {
        // Already gone (navigated away mid-teardown) — nothing to do.
      }
    };
  }

  /** Drop the whole connection — used on logout, so the next session authorizes with its own token. */
  disconnect(): void {
    try {
      this.echo?.disconnect();
    } catch {
      // Nothing useful to do if the socket is already dead.
    }
    this.echo = null;
    this.unavailable = false;
  }
}
