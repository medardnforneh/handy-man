import { Injectable } from '@angular/core';
import { Preferences } from '@capacitor/preferences';

export type ThemeChoice = 'system' | 'light' | 'dark';

const THEME_KEY = 'theme';

/**
 * Owns the app's light/dark preference (doc 08/13). `system` follows the OS; `light`/`dark` stamp
 * `data-theme` on the root, which the generated token stylesheet honours over the media query. The
 * choice is PERSISTED and re-applied on boot (`init()` runs from AppComponent), so it survives a
 * reload — and one service backs both the customer Account and provider Profile so they can't drift.
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  private choice: ThemeChoice = 'system';

  get current(): ThemeChoice {
    return this.choice;
  }

  async init(): Promise<void> {
    const stored = (await Preferences.get({ key: THEME_KEY })).value as ThemeChoice | null;
    this.apply(stored ?? 'system');
  }

  async set(choice: ThemeChoice): Promise<void> {
    this.apply(choice);
    await Preferences.set({ key: THEME_KEY, value: choice });
  }

  private apply(choice: ThemeChoice): void {
    this.choice = choice;
    const root = document.documentElement;
    if (choice === 'system') {
      root.removeAttribute('data-theme');
    } else {
      root.setAttribute('data-theme', choice);
    }
  }
}
