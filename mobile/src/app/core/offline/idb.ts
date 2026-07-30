/**
 * A ~100-line promise wrapper over IndexedDB — the offline substrate for P5-01/02.
 *
 * Why IndexedDB rather than a SQLite plugin: it is the ONE storage engine that behaves identically
 * in every target this codebase ships to (browser PWA, Android WebView, iOS WKWebView) with no
 * native module to build, and it is transactional, asynchronous and effectively unbounded — unlike
 * `localStorage` (which Capacitor Preferences uses on web), whose ~5 MB synchronous string store
 * would block the UI thread on a low-end Android exactly when the network is worst.
 *
 * Two stores, deliberately separate:
 *  - `cache`  — last-known server reads, keyed by a caller-chosen string. Disposable: losing it
 *               costs a spinner.
 *  - `outbox` — writes the user made that the server has not accepted yet. NOT disposable: losing
 *               a row silently drops something the user believes they did, so nothing here is ever
 *               evicted by a size policy — only by an acknowledged send.
 *
 * Every call degrades to a no-op / null when IndexedDB is unavailable (private mode on some
 * browsers, a hostile embedded WebView). Offline support must never be the reason the app breaks
 * for a user who is online.
 */
export const CACHE_STORE = 'cache';
export const OUTBOX_STORE = 'outbox';

const DB_NAME = 'handyman';
const DB_VERSION = 1;

let dbPromise: Promise<IDBDatabase | null> | null = null;

/** Wrap one IDBRequest as a promise. Rejects with the request's error, never with `undefined`. */
function request<T>(req: IDBRequest<T>): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error ?? new Error('IndexedDB request failed'));
  });
}

/**
 * Open (and upgrade) the database once, sharing the connection. A failure resolves to `null` rather
 * than rejecting, so callers can treat "no storage" and "nothing stored" identically.
 */
function open(): Promise<IDBDatabase | null> {
  dbPromise ??= new Promise<IDBDatabase | null>((resolve) => {
    if (typeof indexedDB === 'undefined') {
      resolve(null);
      return;
    }
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains(CACHE_STORE)) {
        db.createObjectStore(CACHE_STORE);
      }
      if (!db.objectStoreNames.contains(OUTBOX_STORE)) {
        // Keyed by the entry's own id (a uuid) so a replay can delete exactly what it sent.
        db.createObjectStore(OUTBOX_STORE, { keyPath: 'id' });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => resolve(null);
    req.onblocked = () => resolve(null);
  });
  return dbPromise;
}

/** Run `work` inside one transaction and resolve when the transaction COMMITS, not when the request does. */
async function transact<T>(
  store: string,
  mode: IDBTransactionMode,
  work: (store: IDBObjectStore) => Promise<T>,
): Promise<T | null> {
  const db = await open();
  if (db === null) {
    return null;
  }
  try {
    const tx = db.transaction(store, mode);
    const result = await work(tx.objectStore(store));
    // A write is only durable once the transaction commits; resolving on the request alone would
    // let the caller report "queued" for a write that a crash a millisecond later would lose.
    await new Promise<void>((resolve, reject) => {
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error ?? new Error('IndexedDB transaction failed'));
      tx.onabort = () => reject(tx.error ?? new Error('IndexedDB transaction aborted'));
    });
    return result;
  } catch {
    return null;
  }
}

export const idb = {
  /** Read one value, or null when absent / storage unavailable. */
  async get<T>(store: string, key: string): Promise<T | null> {
    const value = await transact(store, 'readonly', (s) => request<T | undefined>(s.get(key)));
    return value ?? null;
  },

  /** Write one value. `key` is omitted for the outbox, which is keyed by the record's own `id`. */
  async put<T>(store: string, value: T, key?: string): Promise<void> {
    await transact(store, 'readwrite', (s) => request(key === undefined ? s.put(value) : s.put(value, key)));
  },

  async delete(store: string, key: string): Promise<void> {
    await transact(store, 'readwrite', (s) => request(s.delete(key)));
  },

  /** Every record in a store, in key order. The outbox mints time-ordered ids, so that IS its FIFO. */
  async all<T>(store: string): Promise<T[]> {
    return (await transact(store, 'readonly', (s) => request<T[]>(s.getAll()))) ?? [];
  },

  /** Every key in a store — the cache stores values under caller-chosen keys, so pruning needs these. */
  async keys(store: string): Promise<string[]> {
    const keys = await transact(store, 'readonly', (s) => request<IDBValidKey[]>(s.getAllKeys()));
    return (keys ?? []).map((k) => String(k));
  },

  async clear(store: string): Promise<void> {
    await transact(store, 'readwrite', (s) => request(s.clear()));
  },
};
