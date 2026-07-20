import { Injectable } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { Preferences } from '@capacitor/preferences';

export const SUPPORTED_LOCALES = ['fr', 'en'] as const;
export type Locale = (typeof SUPPORTED_LOCALES)[number];

const DEFAULT_LOCALE: Locale = 'en'; // matches the backend's APP_LOCALE; French is detected/offered
const STORAGE_KEY = 'locale';

/**
 * Owns the app's UI language (doc 09). On first launch we DETECT the device locale and apply it,
 * but do not persist until the user confirms — so the choice is offered, never silently locked in.
 * A device with no supported language lands on English. Persisted via Capacitor Preferences so it
 * survives reinstalls of the web layer.
 */
@Injectable({ providedIn: 'root' })
export class LocaleService {
  private currentLocale: Locale = DEFAULT_LOCALE;
  private chosen = false;

  constructor(private readonly translate: TranslateService) {}

  async init(): Promise<void> {
    const stored = (await Preferences.get({ key: STORAGE_KEY })).value as Locale | null;
    if (stored && this.isSupported(stored)) {
      this.chosen = true;
      await this.apply(stored, false);
    } else {
      await this.apply(this.detectDeviceLocale(), false);
    }
  }

  /** The user picked a language explicitly — apply and remember it. */
  async choose(locale: Locale): Promise<void> {
    this.chosen = true;
    await this.apply(locale, true);
  }

  get current(): Locale {
    return this.currentLocale;
  }

  /** True once the user has explicitly confirmed a language (else we should offer the choice). */
  get hasChosen(): boolean {
    return this.chosen;
  }

  private async apply(locale: Locale, persist: boolean): Promise<void> {
    const use = this.isSupported(locale) ? locale : DEFAULT_LOCALE;
    this.currentLocale = use;
    this.translate.use(use);
    if (persist) {
      await Preferences.set({ key: STORAGE_KEY, value: use });
    }
  }

  private detectDeviceLocale(): Locale {
    const lang = (navigator?.language ?? DEFAULT_LOCALE).slice(0, 2).toLowerCase();
    return this.isSupported(lang) ? lang : DEFAULT_LOCALE;
  }

  private isSupported(value: string): value is Locale {
    return (SUPPORTED_LOCALES as readonly string[]).includes(value);
  }
}
