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

/** The pusher-js wire name for a client event ("whisper") — `client-` prefixed by protocol. */
const TYPING_EVENT = 'client-typing';

/** The slice of pusher-js's channel we use for client events. */
interface RawChannel {
  trigger(event: string, data: unknown): void;
  bind(event: string, handler: (data: { from?: string }) => void): void;
  unbind(event: string, handler: (data: { from?: string }) => void): void;
}

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
  /**
   * The Pusher client we hand to Echo. We build it ourselves rather than letting Echo construct one
   * internally, so connection state is reachable through a reference we own instead of by reaching
   * into Echo's connector — which is private, version-dependent, and silently returned nothing.
   */
  private client: Pusher | null = null;
  /**
   * The RAW pusher channel per engagement, captured at subscribe time.
   *
   * Client events (typing) go through pusher-js directly rather than Echo's `whisper` /
   * `listenForWhisper`: those silently did nothing here — the frame reached the socket but the
   * wrapper never delivered it. Capturing the channel at subscribe time also avoids looking it up
   * later, which is where the previous attempt failed.
   */
  private readonly rawChannels = new Map<string, RawChannel>();
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

      const options = {
        wsHost: environment.reverb.host,
        wsPort: environment.reverb.port,
        wssPort: environment.reverb.port,
        forceTLS: environment.reverb.scheme === 'https',
        enabledTransports: ['ws', 'wss'] as ('ws' | 'wss')[],
        cluster: '',
        // Bearer clients can't use Laravel's session-based /broadcasting/auth (P4-03), so Echo
        // posts to the sanctum-guarded endpoint and attaches the access token itself.
        authEndpoint: `${environment.apiBaseUrl}/broadcasting/auth`,
        auth: {
          headers: {
            Authorization: `Bearer ${tokenStore.get() ?? ''}`,
            Accept: 'application/json',
          },
        },
      };

      this.client = new Pusher(environment.reverb.key, options);
      this.echo = new Echo({
        broadcaster: 'reverb',
        key: environment.reverb.key,
        client: this.client,
        ...options,
      });

      return this.echo;
    } catch {
      this.unavailable = true;
      return null;
    }
  }

  /**
   * Run `handler` whenever the socket comes back after being away (P4-07).
   *
   * Anything that happened while disconnected was never delivered, so a live thread is STALE at
   * this moment — the handler's job is to refetch and reconcile against REST, which is the
   * authoritative record. We fire only on a re-connection, not the first one, because the caller
   * has just loaded over REST anyway.
   */
  onReconnect(handler: () => void): Unsubscribe {
    if (this.connect() === null || this.client === null) {
      return () => undefined;
    }

    const connection = this.client.connection;
    let wasDropped = false;
    const onDown = (): void => {
      wasDropped = true;
    };
    const onUp = (): void => {
      if (wasDropped) {
        wasDropped = false;
        handler();
      }
    };

    connection.bind('unavailable', onDown);
    connection.bind('disconnected', onDown);
    connection.bind('connecting', onDown);
    connection.bind('connected', onUp);

    return () => {
      try {
        connection.unbind('unavailable', onDown);
        connection.unbind('disconnected', onDown);
        connection.unbind('connecting', onDown);
        connection.unbind('connected', onUp);
      } catch {
        // Connection already torn down.
      }
    };
  }

  /**
   * Listen for new messages on an engagement's private channel. Returns an unsubscribe function —
   * or a no-op when realtime isn't available, so callers never need to branch.
   */
  onEngagementMessage(
    engagementId: string,
    handler: (message: LiveMessage) => void,
    onResubscribed?: () => void,
  ): Unsubscribe {
    const echo = this.connect();
    if (echo === null) {
      return () => undefined;
    }

    const name = `engagement.${engagementId}`;
    try {
      const channel = echo.private(name);
      channel.listen('.message.posted', (payload: LiveMessage) => handler(payload));

      // Grab the underlying channel now, while we know it exists — typing rides this same
      // subscription and must never join a second time.
      const raw = this.client?.channel(`private-${name}`) as RawChannel | undefined;
      if (raw !== undefined) {
        this.rawChannels.set(engagementId, raw);
      }

      // Re-subscription is the signal that actually matters: it means we are live on this channel
      // again, whatever route the socket took to get there. Raw connection states are not enough —
      // a server that dies and returns drives `unavailable -> connected`, which in practice did not
      // deliver a usable 'connected' edge, so anything sent meanwhile was silently lost. The FIRST
      // subscription is skipped because the caller has just fetched over REST.
      if (onResubscribed !== undefined) {
        let first = true;
        channel.subscribed(() => {
          if (first) {
            first = false;
            return;
          }
          onResubscribed();
        });
      }
    } catch {
      return () => undefined;
    }

    return () => {
      try {
        this.rawChannels.delete(engagementId);
        // Leave only the private channel — `leave()` would also drop the presence one.
        echo.leaveChannel(`private-${name}`);
      } catch {
        // Already gone (navigated away mid-teardown) — nothing to do.
      }
    };
  }

  /**
   * Tell the other participants we're typing (P4-04).
   *
   * A whisper is a CLIENT event: participant-to-participant through Reverb, never touching the API
   * or the database — right for something this ephemeral and this frequent. Nothing is persisted,
   * so a missed whisper costs nothing. Callers throttle; Reverb rate-limits client events.
   */
  whisperTyping(engagementId: string, from: string): void {
    this.rawChannels.get(engagementId)?.trigger(TYPING_EVENT, { from });
  }

  /** Hear another participant typing. The payload carries who, so we never echo our own whisper. */
  onTyping(engagementId: string, handler: (from: string) => void): Unsubscribe {
    const channel = this.rawChannels.get(engagementId);
    if (channel === undefined) {
      return () => undefined;
    }

    const bound = (payload: { from?: string }): void => handler(payload.from ?? '');
    channel.bind(TYPING_EVENT, bound);

    return () => {
      try {
        channel.unbind(TYPING_EVENT, bound);
      } catch {
        // Channel already left.
      }
    };
  }

  /**
   * Who else is on this engagement right now (P4-04).
   *
   * A presence channel is separate from the private one that carries messages — same authorization
   * rule, two subscriptions. Keeping them apart means presence can't disturb the message path,
   * which is the one that must never break.
   *
   * `handler` receives the count of OTHER members, so the caller never has to filter itself out.
   * Presence is a live signal only: nothing is persisted, and losing the socket simply means we
   * stop claiming anyone is there rather than claiming they left.
   */
  onPresence(engagementId: string, handler: (othersPresent: number) => void): Unsubscribe {
    const echo = this.connect();
    if (echo === null) {
      return () => undefined;
    }

    const name = `engagement.${engagementId}`;
    const others = new Set<string>();
    const report = (): void => handler(others.size);

    try {
      echo.join(name)
        .here((members: { id?: string }[]) => {
          others.clear();
          for (const m of members) {
            if (m.id !== undefined && m.id !== this.selfId) {
              others.add(m.id);
            }
          }
          report();
        })
        .joining((member: { id?: string }) => {
          if (member.id !== undefined && member.id !== this.selfId) {
            others.add(member.id);
            report();
          }
        })
        .leaving((member: { id?: string }) => {
          if (member.id !== undefined) {
            others.delete(member.id);
            report();
          }
        });
    } catch {
      return () => undefined;
    }

    return () => {
      try {
        // `leaveChannel`, NOT `leave`: Echo's `leave(name)` drops the public, private AND presence
        // variants, which would tear down the message subscription that shares this engagement.
        echo.leaveChannel(`presence-${name}`);
      } catch {
        // Already gone.
      }
    };
  }

  /** Our own user id, so presence can filter us out of the member list. Set by the caller on load. */
  private selfId = '';

  setSelfId(userId: string): void {
    this.selfId = userId;
  }

  /** Drop the whole connection — used on logout, so the next session authorizes with its own token. */
  disconnect(): void {
    try {
      this.echo?.disconnect();
    } catch {
      // Nothing useful to do if the socket is already dead.
    }
    this.echo = null;
    this.client = null;
    this.rawChannels.clear();
    this.unavailable = false;
  }
}
