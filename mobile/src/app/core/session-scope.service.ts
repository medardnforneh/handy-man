import { Injectable } from '@angular/core';

/**
 * The registry of state that belongs to ONE signed-in session.
 *
 * Logging out cleared the tokens, the offline cache, the write queue and the socket — everything
 * that lives on disk or on the wire — and left the services' in-memory state untouched. Those
 * services are root singletons, and on the web nothing reloads the page between sessions, so the
 * previous person's name, phone, saved addresses and jobs survived into the next login and sat on
 * screen until each screen's own fetch happened to replace them.
 *
 * That is the exact failure the offline cache is cleared to prevent, described in `logout()` as
 * "it must not survive into the next login on a shared phone" — the cache was cleared and the copy
 * in memory was not.
 *
 * A registry rather than direct calls, because the dependency has to point this way: `core` must
 * not import the customer and provider layers to be able to reset them. Each feature service
 * registers what it owns; `AuthService` empties the lot without knowing what any of it is.
 */
@Injectable({ providedIn: 'root' })
export class SessionScope {
  private readonly resets = new Set<() => void>();

  /**
   * Register state to be dropped when the session ends. Called from a service's constructor, so
   * anything that holds session data is registered by virtue of existing.
   */
  register(reset: () => void): void {
    this.resets.add(reset);
  }

  /**
   * End the session's in-memory life. Every reset runs even if one throws — a service that fails to
   * clean up must not keep the others from doing it, since the whole point is that nothing is left
   * behind for the next person.
   */
  clear(): void {
    for (const reset of this.resets) {
      try {
        reset();
      } catch {
        // Deliberately swallowed: see above.
      }
    }
  }
}
