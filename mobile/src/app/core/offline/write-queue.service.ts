import { DestroyRef, Injectable, effect, inject, signal, untracked } from '@angular/core';
import { api } from '../../api/client';
import { uuid } from '../uuid';
import { ConnectivityService } from './connectivity.service';
import { OUTBOX_STORE, idb } from './idb';

/** What a queued write is, in the user's terms — drives the copy shown while it waits. */
export type QueuedWriteKind =
  | 'message'
  | 'status'
  | 'check_in'
  | 'check_out'
  | 'milestone_approval';

/** How a submitted write ended up. `queued` means accepted locally and not yet acknowledged. */
export type WriteOutcome = 'sent' | 'queued' | 'failed';

/**
 * What `submit` answers with. `detail` carries the server's problem+json explanation on a refusal —
 * a check-in rejected because the engagement is remote deserves to say so, not "something failed".
 */
export interface WriteResult {
  outcome: WriteOutcome;
  detail?: string;
}

/** A write the user made, durable until the server acknowledges it. */
export interface QueuedWrite {
  /** Time-ordered id — also the IndexedDB key, which is what makes `getAll()` return FIFO order. */
  id: string;
  kind: QueuedWriteKind;
  method: 'POST' | 'PATCH' | 'DELETE';
  /** The OpenAPI path TEMPLATE (e.g. `/jobs/{job}/messages`), not an interpolated URL. */
  path: string;
  pathParams: Record<string, string>;
  body?: unknown;
  /**
   * Minted ONCE, here, and reused on every replay for the life of this entry. This single field is
   * what makes "airplane mode → replay once, not twice" true: the server's idempotency middleware
   * (P0-06) keys on it, so a request that reached the API but whose response we never saw is
   * replayed into a stored-response REPLAY, not a second job / second message / second payment.
   */
  idempotencyKey: string;
  queuedAt: number;
  attempts: number;
  /** Epoch ms; the flush loop skips an entry until its backoff has elapsed. */
  nextAttemptAt: number;
  status: 'pending' | 'failed';
  lastError?: string;
}

/** What a caller hands in; everything else is the queue's business. */
export interface WriteSpec {
  kind: QueuedWriteKind;
  method: 'POST' | 'PATCH' | 'DELETE';
  path: string;
  pathParams?: Record<string, string>;
  body?: unknown;
}

/**
 * The one place a dynamic path meets the generated client. openapi-fetch's types are keyed by
 * literal path strings, which a queue read back from storage cannot be — so the cast lives here,
 * once, and nowhere else. Going through the client (rather than a bare `fetch`) is deliberate: the
 * queue inherits the Bearer, the app-version header and the 401-refresh-and-replay from P1-03.
 */
type DynamicWriter = (
  path: string,
  init: Record<string, unknown>,
) => Promise<{ data?: unknown; error?: unknown; response: Response }>;

const WRITERS: Record<QueuedWrite['method'], DynamicWriter> = {
  POST: api.POST as unknown as DynamicWriter,
  PATCH: api.PATCH as unknown as DynamicWriter,
  DELETE: api.DELETE as unknown as DynamicWriter,
};

/** Backoff between attempts: 5s, 10s, 20s … capped at 5 min so a long outage doesn't spin the radio. */
const BASE_BACKOFF_MS = 5_000;
const MAX_BACKOFF_MS = 300_000;
/**
 * A server that keeps erroring is not a network problem. After this many failed attempts the entry
 * stops retrying and becomes visible as failed — an invisible forever-retry is worse than an
 * honest "this didn't send".
 */
const MAX_ATTEMPTS = 8;
/** While anything is waiting, re-check on this cadence — a backoff can expire with no other event. */
const TICK_MS = 15_000;

/**
 * Pull the human-readable line out of an RFC 7807 problem+json body (P0-08). A refusal here is
 * usually a real domain rule — "this engagement is remote, there is nothing to check in to" — and
 * the worker deserves to read that rather than a status code.
 */
function problemDetail(error: unknown): string | undefined {
  if (typeof error !== 'object' || error === null) {
    return undefined;
  }
  const problem = error as { detail?: unknown; title?: unknown };
  return [problem.detail, problem.title]
    .find((v): v is string => typeof v === 'string' && v.trim() !== '');
}

/** A time-ordered id: sorts by queue time in IndexedDB's key order, still globally unique. */
function orderedId(): string {
  return `${Date.now().toString().padStart(14, '0')}-${uuid()}`;
}

/**
 * The offline write queue (P5-02).
 *
 * Every unsafe write that the user should not have to be online to make goes through `submit`:
 * it is **persisted before it is attempted**, so a crash or a killed app mid-request loses nothing,
 * and it is replayed FIFO — one at a time — when the API is reachable again. Ordering matters
 * (two chat messages must not swap), so there is deliberately no parallelism here.
 *
 * Writes that must return server state to continue (creating a job, whose id the next screen needs)
 * are NOT queued — a queue can only promise eventual delivery, never an id — and stay direct calls.
 */
@Injectable({ providedIn: 'root' })
export class WriteQueue {
  private readonly connectivity = inject(ConnectivityService);

  /** Entries still owed to the server — the count the UI shows. */
  readonly pending = signal<QueuedWrite[]>([]);
  /** Entries the server refused; they need a human decision, not another retry. */
  readonly failed = signal<QueuedWrite[]>([]);

  private flushing = false;
  private timer: ReturnType<typeof setTimeout> | null = null;
  /** Set once the injector is torn down; a flush in flight stops rather than outliving the app. */
  private destroyed = false;
  /**
   * Resolves once the outbox has been read off disk. Every flush waits on it, because a read that
   * finishes AFTER a write has already been sent and deleted would put that write back into the
   * queue from its own stale snapshot — and send it a second time.
   */
  private readonly ready: Promise<void>;
  /** Resolvers for in-flight `submit` calls, so a caller learns 'sent' vs 'queued' for ITS write. */
  private readonly waiters = new Map<string, (result: WriteResult) => void>();

  constructor() {
    this.ready = this.restore();
    void this.ready.then(() => this.flush());
    // Connectivity coming back is the main flush trigger; the tick covers backoff expiry.
    effect(() => {
      this.connectivity.restored();
      // `untracked` is load-bearing, not decoration: flushing READS `pending` and then WRITES it,
      // and a signal written inside its own effect's tracking context re-triggers that effect
      // forever. (Array identity guarantees it — a fresh `[]` is never `Object.is`-equal to the last
      // one, so even an empty queue spins.) Only `restored` may schedule this effect.
      untracked(() => void this.flushNow());
    });
    // The retry timer would otherwise keep firing after the injector is gone — harmless in the app,
    // where this service lives as long as the app does, but a real leak anywhere else.
    inject(DestroyRef).onDestroy(() => {
      this.destroyed = true;
      if (this.timer !== null) {
        clearTimeout(this.timer);
        this.timer = null;
      }
    });
  }

  /**
   * Send everything owed right now, ignoring every backoff.
   *
   * A backoff is a guess about when the network might work again; "the network just came back" is
   * knowledge. Sitting out a five-minute wait after the signal returns would be the difference
   * between a message arriving now and arriving after the user has given up and closed the app.
   */
  async flushNow(): Promise<void> {
    await this.ready;
    const owed = this.pending();
    if (owed.length === 0) {
      return; // nothing to revive, and no pointless signal write
    }
    const revived = owed.map((e) => ({ ...e, nextAttemptAt: 0 }));
    await Promise.all(revived.map((e) => idb.put(OUTBOX_STORE, e)));

    // PATCH the queue, never replace it. Persisting the revived rows above is asynchronous, and an
    // in-flight flush can send and remove an entry across that gap — writing `owed` back wholesale
    // would put the sent one back and send it a second time. (Observed: a reconnect mid-flush
    // replayed the first message twice.) Mapping by id touches only entries that are still owed.
    const revivedById = new Map(revived.map((e) => [e.id, e]));
    this.pending.update((current) => current.map((e) => revivedById.get(e.id) ?? e));

    await this.flush();
  }

  /**
   * Accept a write on the user's behalf. Resolves `sent` when the server acknowledged it during
   * this call, `queued` when it is durably stored and will be replayed, `failed` when the server
   * refused it outright (a 4xx — replaying that would only fail again).
   */
  async submit(spec: WriteSpec): Promise<WriteResult> {
    const entry: QueuedWrite = {
      id: orderedId(),
      kind: spec.kind,
      method: spec.method,
      path: spec.path,
      pathParams: spec.pathParams ?? {},
      body: spec.body,
      idempotencyKey: uuid(),
      queuedAt: Date.now(),
      attempts: 0,
      nextAttemptAt: 0,
      status: 'pending',
    };

    await idb.put(OUTBOX_STORE, entry);
    this.pending.update((list) => [...list, entry]);

    const result = new Promise<WriteResult>((resolve) => this.waiters.set(entry.id, resolve));
    // Wait only for the queue to make what progress it can right now, then answer. If this entry
    // is still owed after that (offline, or behind an older one), the honest answer is `queued` —
    // the caller must not block until the network comes back.
    await this.flush();
    this.wake(entry.id, { outcome: 'queued' }); // a no-op if the flush already settled it
    return result;
  }

  /** Drop a failed entry the user has acknowledged. It is gone — the server never accepted it. */
  async discard(id: string): Promise<void> {
    await idb.delete(OUTBOX_STORE, id);
    this.failed.update((list) => list.filter((e) => e.id !== id));
    this.pending.update((list) => list.filter((e) => e.id !== id));
  }

  /** Give a failed entry one more chance — same idempotency key, so a retry still can't duplicate. */
  async retry(id: string): Promise<void> {
    const entry = this.failed().find((e) => e.id === id);
    if (entry === undefined) {
      return;
    }
    const revived: QueuedWrite = { ...entry, status: 'pending', attempts: 0, nextAttemptAt: 0 };
    await idb.put(OUTBOX_STORE, revived);
    this.failed.update((list) => list.filter((e) => e.id !== id));
    this.pending.update((list) => [...list, revived]);
    void this.flush();
  }

  /**
   * Drop everything owed. Called on logout only: a queued write was authorized by the session that
   * made it, and replaying it under a different Bearer would attribute one user's action to another.
   */
  async clear(): Promise<void> {
    await idb.clear(OUTBOX_STORE);
    this.pending.set([]);
    this.failed.set([]);
  }

  /** Re-hydrate the outbox on boot: whatever the last session didn't get out still owes the server. */
  private async restore(): Promise<void> {
    const all = await idb.all<QueuedWrite>(OUTBOX_STORE);
    all.sort((a, b) => a.id.localeCompare(b.id));

    // MERGE, never replace. Reading the store is asynchronous, and the user can submit a write in
    // the moment between the read starting and finishing — overwriting the signal would drop that
    // write from the live queue (it would sit on disk until the next launch, which is exactly the
    // kind of "my message never sent" that this whole feature exists to prevent).
    const absorb = (current: QueuedWrite[], status: QueuedWrite['status']): QueuedWrite[] => {
      const known = new Set(current.map((e) => e.id));
      return [...current, ...all.filter((e) => e.status === status && !known.has(e.id))]
        .sort((a, b) => a.id.localeCompare(b.id));
    };
    this.pending.update((current) => absorb(current, 'pending'));
    this.failed.update((current) => absorb(current, 'failed'));
  }

  /**
   * Send everything owed, oldest first, strictly one at a time. Re-entrant calls are collapsed by
   * `flushing`: a second caller would otherwise replay an entry the first is mid-flight on, which
   * is precisely the double-send the idempotency key exists to prevent — better not to race at all.
   */
  private async flush(): Promise<void> {
    await this.ready;
    if (this.flushing || this.destroyed) {
      return;
    }
    this.flushing = true;
    try {
      for (;;) {
        // Strictly the OLDEST entry — never skip past one that is waiting out its backoff. Skipping
        // would let a later message overtake an earlier one, and a thread that reorders itself is a
        // worse failure than one that pauses.
        const next = [...this.pending()].sort((a, b) => a.id.localeCompare(b.id))[0];
        if (next === undefined || next.nextAttemptAt > Date.now() || this.destroyed) {
          break;
        }
        const keepGoing = await this.attempt(next);
        if (!keepGoing) {
          break; // the transport is down — stop hammering it; connectivity will wake us
        }
      }
    } finally {
      this.flushing = false;
      this.scheduleTick();
    }
  }

  /** One attempt at one entry. Returns false when the transport is down and the loop should stop. */
  private async attempt(entry: QueuedWrite): Promise<boolean> {
    try {
      const { response, error } = await WRITERS[entry.method](entry.path, {
        params: {
          path: entry.pathParams,
          header: { 'Idempotency-Key': entry.idempotencyKey },
        },
        ...(entry.body === undefined ? {} : { body: entry.body }),
      });

      // The API answered, whatever it said — so the network is up.
      this.connectivity.markReachable();

      if (response.ok) {
        await this.settle(entry, 'sent');
        return true;
      }
      // 5xx and 429 are the server's problem and worth replaying; every other 4xx is a refusal
      // that would fail identically forever (a validation error, a gone job, a lost authorization).
      if (response.status >= 500 || response.status === 429) {
        return await this.backoff(entry, `HTTP ${response.status}`);
      }
      await this.settle(entry, 'failed', problemDetail(error) ?? `HTTP ${response.status}`);
      return true;
    } catch {
      // A THROWN fetch is a transport failure — this is the real "offline", not an error status.
      this.connectivity.markUnreachable();
      await this.backoff(entry, 'network');
      return false;
    }
  }

  /** Record a failed attempt and push the next one out; retire the entry once it has had enough. */
  private async backoff(entry: QueuedWrite, reason: string): Promise<boolean> {
    const attempts = entry.attempts + 1;
    // A transport failure never exhausts the budget: being offline for a day is not an error, and
    // retiring a message because the user was in a basement would lose it for no reason.
    if (reason !== 'network' && attempts >= MAX_ATTEMPTS) {
      await this.settle(entry, 'failed', reason);
      return true;
    }
    const delay = Math.min(BASE_BACKOFF_MS * 2 ** (attempts - 1), MAX_BACKOFF_MS);
    const updated: QueuedWrite = {
      ...entry,
      attempts: reason === 'network' ? entry.attempts : attempts,
      nextAttemptAt: Date.now() + delay,
      lastError: reason,
    };
    await idb.put(OUTBOX_STORE, updated);
    this.pending.update((list) => list.map((e) => (e.id === updated.id ? updated : e)));
    // The caller shouldn't wait out a backoff — tell it the write is safely queued and move on.
    this.wake(entry.id, { outcome: 'queued' });
    return true;
  }

  /** Terminal state: acknowledged (row deleted) or refused (row kept, visible, retryable by hand). */
  private async settle(entry: QueuedWrite, outcome: 'sent' | 'failed', reason?: string): Promise<void> {
    if (outcome === 'sent') {
      await idb.delete(OUTBOX_STORE, entry.id);
    } else {
      const dead: QueuedWrite = { ...entry, status: 'failed', lastError: reason };
      await idb.put(OUTBOX_STORE, dead);
      this.failed.update((list) => [...list, dead]);
    }
    this.pending.update((list) => list.filter((e) => e.id !== entry.id));
    this.wake(entry.id, { outcome, detail: reason });
  }

  private wake(id: string, result: WriteResult): void {
    const waiter = this.waiters.get(id);
    if (waiter !== undefined) {
      this.waiters.delete(id);
      waiter(result);
    }
  }

  /** Keep exactly one timer alive while anything is owed, and none when the queue is empty. */
  private scheduleTick(): void {
    if (this.timer !== null) {
      clearTimeout(this.timer);
      this.timer = null;
    }
    if (this.pending().length === 0 || this.destroyed) {
      return;
    }
    this.timer = setTimeout(() => {
      this.timer = null;
      void this.flush();
    }, TICK_MS);
  }
}
