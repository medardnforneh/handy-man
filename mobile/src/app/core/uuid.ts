/**
 * A v4 UUID, on every WebView this app ships to.
 *
 * `crypto.randomUUID()` only exists from Chrome 92. Android's System WebView is updatable, but it
 * is NOT always updated — an emulator image without Play, or a cheap handset that has never had a
 * WebView update, can be several versions behind. That is exactly the device this product targets.
 *
 * The failure this caused was invisible: every unsafe write mints an Idempotency-Key with this, so
 * on an older WebView `crypto.randomUUID is not a function` threw inside `AuthService.requestOtp` —
 * which catches and walks on to the next screen. The app moved to the code-entry screen having
 * never asked for a code. (Observed on an Android 12 emulator, WebView Chrome 91.)
 *
 * `crypto.getRandomValues` is available far earlier and is what actually matters here: these ids
 * are idempotency keys, so they must be unguessable and must not collide. `Math.random` is the last
 * resort only — a key that repeats would make two different writes look like a replay of one.
 */
export function uuid(): string {
  const c = globalThis.crypto as Crypto | undefined;

  if (typeof c?.randomUUID === 'function') {
    return c.randomUUID();
  }

  const bytes = new Uint8Array(16);
  if (typeof c?.getRandomValues === 'function') {
    c.getRandomValues(bytes);
  } else {
    for (let i = 0; i < bytes.length; i++) {
      bytes[i] = Math.floor(Math.random() * 256);
    }
  }

  // Version 4, variant 1 — the bits a v4 UUID is required to pin.
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;

  const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
