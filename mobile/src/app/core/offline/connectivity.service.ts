import { DestroyRef, Injectable, inject, signal } from '@angular/core';

/**
 * Is the app reachable-online right now? (P5-02.)
 *
 * `navigator.onLine` is the only connectivity signal available identically in a browser, an Android
 * WebView and WKWebView, so it is the base — but it is famously optimistic: it means "an interface
 * is up", not "the API answered". On the networks this product targets that gap is the normal case,
 * not the edge case (a phone camped on a cell with no usable data reports `true`).
 *
 * So the truth here is REPORTED BY THE REQUESTS THEMSELVES: whoever talks to the API tells us
 * whether it answered (`markReachable`) or the fetch failed at the transport (`markUnreachable`).
 * The browser's own events are treated as hints that something changed and are worth a retry, never
 * as proof. The result is one signal the UI can trust enough to say "you're offline".
 */
@Injectable({ providedIn: 'root' })
export class ConnectivityService {
  /** True when we believe a request would reach the API. Starts from the browser's optimistic view. */
  readonly online = signal(typeof navigator === 'undefined' || navigator.onLine);

  /** Bumped whenever connectivity is regained, so listeners (the write queue) can flush on it. */
  readonly restored = signal(0);

  constructor() {
    if (typeof window === 'undefined') {
      return;
    }
    const onOnline = () => this.markReachable();
    // An `offline` event is definitive in the negative direction (the interface really is down);
    // only the positive direction is untrustworthy, so this one is applied directly.
    const onOffline = () => this.setOnline(false);
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    inject(DestroyRef).onDestroy(() => {
      window.removeEventListener('online', onOnline);
      window.removeEventListener('offline', onOffline);
    });
  }

  /** A request reached the API — the strongest possible evidence that we are online. */
  markReachable(): void {
    this.setOnline(true);
  }

  /**
   * A request failed at the transport (a thrown `fetch`, not an HTTP error status). An HTTP 500 is
   * NOT this: the server answered, so the network is fine and calling it "offline" would be a lie.
   */
  markUnreachable(): void {
    this.setOnline(false);
  }

  private setOnline(value: boolean): void {
    const was = this.online();
    this.online.set(value);
    if (value && !was) {
      this.restored.update((n) => n + 1);
    }
  }
}
