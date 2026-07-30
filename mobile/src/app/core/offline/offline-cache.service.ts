import { Injectable, inject } from '@angular/core';
import { ConnectivityService } from './connectivity.service';
import { CACHE_STORE, idb } from './idb';

/** What is stored per key: the payload plus when the server last told us it was true. */
interface CacheEnvelope<T> {
  value: T;
  cachedAt: number;
}

/** The result of a read-through: the data, and whether it came from the server just now. */
export interface CachedRead<T> {
  value: T | null;
  /** True when this came from the API on this call; false when it is a remembered copy. */
  fresh: boolean;
  /** Epoch ms of the server read this data came from — null when there was never one. */
  cachedAt: number | null;
}

/**
 * Entries older than this are dropped on boot. Long enough that a week away still opens to
 * something; short enough that the app never shows a picture of the world from last month.
 */
const MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000;

/**
 * Read-through cache for the screens that must open with something (P5-02).
 *
 * The rule is: **the last thing the server said beats a fixture, and a fixture beats a spinner.**
 * Before this, every screen's `catch` fell back to demo data, so a customer opening their jobs list
 * on a dead connection saw someone else's fictional plumbing job — worse than useless, actively
 * misleading. Now the same catch path returns their own last-known jobs, with the age of that read
 * available so the UI can say so.
 *
 * Only server reads are cached, and only the shapes the app already maps — nothing here decides
 * what is true, it only remembers what the API last answered.
 */
@Injectable({ providedIn: 'root' })
export class OfflineCache {
  private readonly connectivity = inject(ConnectivityService);

  constructor() {
    void this.prune();
  }

  /**
   * Run `loader`, cache what it returns, and fall back to the remembered copy when it fails.
   *
   * A thrown loader is treated as "no answer" — which covers both offline and a 4xx, deliberately:
   * from the screen's point of view both mean "I could not refresh this", and showing the last
   * known state is the right answer for either.
   */
  async through<T>(key: string, loader: () => Promise<T>): Promise<CachedRead<T>> {
    try {
      const value = await loader();
      const cachedAt = Date.now();
      await idb.put<CacheEnvelope<T>>(CACHE_STORE, { value, cachedAt }, key);
      this.connectivity.markReachable();
      return { value, fresh: true, cachedAt };
    } catch {
      const stored = await idb.get<CacheEnvelope<T>>(CACHE_STORE, key);
      return stored === null
        ? { value: null, fresh: false, cachedAt: null }
        : { value: stored.value, fresh: false, cachedAt: stored.cachedAt };
    }
  }

  /** The remembered copy alone, without attempting the network — for an instant first paint. */
  async peek<T>(key: string): Promise<CachedRead<T>> {
    const stored = await idb.get<CacheEnvelope<T>>(CACHE_STORE, key);
    return stored === null
      ? { value: null, fresh: false, cachedAt: null }
      : { value: stored.value, fresh: false, cachedAt: stored.cachedAt };
  }

  /** Forget everything. Called on logout — a cached job list is this user's data, not the device's. */
  async clear(): Promise<void> {
    await idb.clear(CACHE_STORE);
  }

  /** Drop stale entries on boot so the store can't grow without bound on a shared phone. */
  private async prune(): Promise<void> {
    const cutoff = Date.now() - MAX_AGE_MS;
    for (const key of await idb.keys(CACHE_STORE)) {
      const entry = await idb.get<CacheEnvelope<unknown>>(CACHE_STORE, key);
      if (entry !== null && entry.cachedAt < cutoff) {
        await idb.delete(CACHE_STORE, key);
      }
    }
  }
}
