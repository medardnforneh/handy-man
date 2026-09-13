import { signal } from '@angular/core';

/**
 * The force-update kill switch, client side (build plan P0-08).
 *
 * The server refuses a build below its minimum with 426 Upgrade Required on EVERY request. Left
 * unhandled, that is the worst possible failure: nothing crashes, every read quietly falls back to
 * its empty state, and a person is left holding an app that looks broken with no idea why or what
 * to do. So the transport records the first 426 here and the shell stops the app on it — one
 * screen, one action — because a build the server has retired must not keep acting on stale
 * contracts (a payment flow that changed shape is the case the switch exists for).
 *
 * A plain signal outside DI so the API client, which is a module-level singleton created before
 * the injector, can set it.
 */
export const upgradeRequired = signal<{ minVersion: string | null } | null>(null);

/**
 * A hundred refused requests are one fact — but the first refusal the app sees may be a request it
 * was already abandoning (the boot fetches race, and an aborted response has no body to read), so
 * a later refusal that does carry the minimum is allowed to fill it in. The fact never un-sets.
 */
export function markUpgradeRequired(minVersion: string | null): void {
  const current = upgradeRequired();
  if (current === null) {
    upgradeRequired.set({ minVersion });
  } else if (current.minVersion === null && minVersion !== null) {
    upgradeRequired.set({ minVersion });
  }
}
