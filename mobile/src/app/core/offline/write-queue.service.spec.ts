import { TestBed } from '@angular/core/testing';
import { ConnectivityService } from './connectivity.service';
import { OUTBOX_STORE, idb } from './idb';
import { QueuedWrite, WriteQueue } from './write-queue.service';

/**
 * The P5-02 acceptance test: **airplane mode → queued actions replay once, not twice.**
 *
 * This runs in a real browser (Karma/Chrome), so IndexedDB is real and the writes really go through
 * the generated `openapi-fetch` client — only the transport (`fetch`) is stood in for, which is
 * exactly the layer a dead network replaces. What is asserted is the property the whole design
 * exists for: an entry keeps ONE idempotency key from the first attempt to the last, so a server
 * that already accepted a write recognises the replay instead of doing it again.
 */
describe('WriteQueue (P5-02 offline write queue)', () => {
  /** Every request the transport saw, in order. */
  let seen: { url: string; key: string | null; body: string }[];
  /** Flipped to simulate the radio coming back. */
  let reachable: boolean;
  /** Requests the fake server accepted, keyed by idempotency key — its dedupe table. */
  let accepted: Map<string, number>;

  const message = (body: string) => ({
    kind: 'message' as const,
    method: 'POST' as const,
    path: '/jobs/{job}/messages',
    pathParams: { job: 'job-1' },
    body: { body },
  });

  beforeEach(async () => {
    seen = [];
    accepted = new Map();
    reachable = false;
    await idb.clear(OUTBOX_STORE);

    spyOn(window, 'fetch').and.callFake(async (input: RequestInfo | URL, init?: RequestInit) => {
      const request = new Request(input, init);
      const key = request.headers.get('Idempotency-Key');
      seen.push({ url: request.url, key, body: await request.clone().text() });

      if (!reachable) {
        // What a dead network actually looks like to fetch: a thrown TypeError, not a status.
        throw new TypeError('Failed to fetch');
      }
      // Stand-in for the server's idempotency middleware (P0-06): a repeated key is a REPLAY.
      accepted.set(key ?? '', (accepted.get(key ?? '') ?? 0) + 1);
      return new Response('{"data":{}}', {
        status: 201,
        headers: { 'Content-Type': 'application/json' },
      });
    });

    TestBed.configureTestingModule({});
  });

  afterEach(async () => {
    await idb.clear(OUTBOX_STORE);
  });

  it('queues a write while the transport is down and replays it exactly once, with the same key', async () => {
    const queue = TestBed.inject(WriteQueue);

    const result = await queue.submit(message('hello'));

    expect(result.outcome).toBe('queued');
    expect(queue.pending().length).toBe(1);
    expect(seen.length).toBe(1); // it did try
    const key = seen[0].key;
    expect(key).toBeTruthy();

    // It survives a restart: the row is on disk, not just in memory.
    const stored = await idb.all<QueuedWrite>(OUTBOX_STORE);
    expect(stored.length).toBe(1);
    expect(stored[0].idempotencyKey).toBe(key!);

    reachable = true;
    TestBed.inject(ConnectivityService).markReachable();
    await queue.flushNow();

    expect(queue.pending().length).toBe(0);
    expect(await idb.all(OUTBOX_STORE)).toEqual([]);
    // Two attempts total — and the SECOND carried the same key as the first, which is what stops a
    // request the server already handled from being applied twice.
    expect(seen.length).toBe(2);
    expect(seen[1].key).toBe(key);
    expect(accepted.size).toBe(1);
    expect(accepted.get(key!)).toBe(1);
  });

  it('does not create a second write when the response is lost after the server accepted it', async () => {
    const queue = TestBed.inject(WriteQueue);
    let dropNextResponse = true;

    (window.fetch as jasmine.Spy).and.callFake(async (input: RequestInfo | URL, init?: RequestInit) => {
      const request = new Request(input, init);
      const key = request.headers.get('Idempotency-Key') ?? '';
      seen.push({ url: request.url, key, body: await request.clone().text() });
      // The worst case for a queue: the server DID take the write, then the connection died before
      // the answer got back. The client cannot tell this apart from "it never arrived".
      accepted.set(key, (accepted.get(key) ?? 0) + 1);
      if (dropNextResponse) {
        dropNextResponse = false;
        throw new TypeError('Failed to fetch');
      }
      return new Response('{"data":{}}', { status: 201, headers: { 'Content-Type': 'application/json' } });
    });

    await queue.submit(message('paid the deposit'));
    expect(queue.pending().length).toBe(1);

    await queue.flushNow();

    expect(queue.pending().length).toBe(0);
    expect(seen.length).toBe(2);
    // Both attempts carried one key, so the real server would have deduped the replay — the
    // customer is not charged twice, and the thread does not show the message twice.
    expect(new Set(seen.map((s) => s.key)).size).toBe(1);
    expect(accepted.size).toBe(1);
  });

  it('replays in the order the user made them', async () => {
    const queue = TestBed.inject(WriteQueue);

    await queue.submit(message('first'));
    await queue.submit(message('second'));
    await queue.submit(message('third'));
    expect(queue.pending().length).toBe(3);

    seen = [];
    reachable = true;
    await queue.flushNow();

    expect(queue.pending().length).toBe(0);
    expect(seen.map((s) => JSON.parse(s.body).body)).toEqual(['first', 'second', 'third']);
  });

  it('stops retrying a write the server refused, and surfaces its reason', async () => {
    const queue = TestBed.inject(WriteQueue);
    reachable = true;

    (window.fetch as jasmine.Spy).and.callFake(async () =>
      new Response(JSON.stringify({ title: 'Unprocessable', detail: 'This engagement is remote.' }), {
        status: 422,
        headers: { 'Content-Type': 'application/problem+json' },
      }));

    const result = await queue.submit({
      kind: 'check_in',
      method: 'POST',
      path: '/engagements/{engagement}/check-in',
      pathParams: { engagement: 'eng-1' },
      body: {},
    });

    expect(result.outcome).toBe('failed');
    expect(result.detail).toBe('This engagement is remote.');
    expect(queue.pending().length).toBe(0);
    // Kept, not silently dropped: the worker gets to see it and decide.
    expect(queue.failed().length).toBe(1);
  });

  it('forgets everything owed on logout, so it can never be replayed as another user', async () => {
    const queue = TestBed.inject(WriteQueue);

    await queue.submit(message('mine'));
    expect((await idb.all(OUTBOX_STORE)).length).toBe(1);

    await queue.clear();

    expect(queue.pending().length).toBe(0);
    expect(await idb.all(OUTBOX_STORE)).toEqual([]);
  });
});
