import { Injectable, inject } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { Preferences } from '@capacitor/preferences';
import { ApiService } from '../api/api.service';
import { AuthService } from './auth.service';
import { OfflineCache } from './offline/offline-cache.service';

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
  private readonly api = inject(ApiService);
  private readonly auth = inject(AuthService);
  private readonly cache = inject(OfflineCache);
  private readonly translate = inject(TranslateService);
  private currentLocale: Locale = DEFAULT_LOCALE;
  private chosen = false;

  async init(): Promise<void> {
    const stored = (await Preferences.get({ key: STORAGE_KEY })).value as Locale | null;
    if (stored && this.isSupported(stored)) {
      this.chosen = true;
      await this.apply(stored, false);
    } else {
      await this.apply(this.detectDeviceLocale(), false);
    }
  }

  /**
   * Adopt the ACCOUNT's language at launch, in every section.
   *
   * {@see init()} only detects what the DEVICE speaks. The account's own locale is the considered
   * value, and until now the only thing that reconciled the two was `CustomerService.loadMe()` —
   * so it happened in the customer section and nowhere else. The provider section never adopted it,
   * and neither did `/safety`, which sits outside both shells on purpose: cold-starting onto the
   * emergency screen (a deep link, a push, a route the packaged app restored) showed a French
   * account English instructions for the panic button.
   *
   * The remembered `me` answers first, so a launch with no network still speaks the right language
   * and costs no request. Only a device that has never cached one asks the server. The customer
   * section refetches `me` moments later either way and reconciles again, which is what corrects a
   * locale changed on another device.
   *
   * Silent throughout: a language is not worth an error message, and this runs before any screen.
   */
  async adoptAccountLocale(): Promise<void> {
    await this.auth.ensureReady();
    if (!this.auth.authed()) {
      return;
    }

    const remembered = await this.cache.peek<{ locale?: string }>('me');
    if (remembered.value?.locale !== undefined) {
      await this.reconcile(remembered.value.locale);
      return;
    }

    try {
      await this.reconcile((await this.api.me()).locale);
    } catch {
      // No session yet, or offline — the device language stands and the next launch tries again.
    }
  }

  /**
   * The user picked a language explicitly — apply it, remember it on the device, and tell the
   * server (P1-05b). The server copy is what bilingual payloads (skill labels) and outbound comms
   * follow, so skipping it leaves the app's chrome and its data speaking different languages.
   * Best-effort: an offline device still switches language immediately.
   */
  async choose(locale: Locale): Promise<void> {
    this.chosen = true;
    await this.apply(locale, true);

    try {
      await this.api.setLocalePreference(locale);
    } catch {
      // No session / offline — the device preference still holds and re-syncs on the next change.
    }
  }

  /**
   * Reconcile the app's language with the account's, once `GET /auth/me` has said what the account
   * thinks it is.
   *
   * These two could disagree, and did: the server's locale precedence puts the user's STORED
   * preference above the Accept-Language header, so a phone showing English chrome was being sent
   * French skill labels, French follow-ups and French push copy — the exact split
   * {@see choose()} exists to prevent, reached by never choosing. `apply()` on first launch only
   * detects a device language; it tells nobody.
   *
   * Whoever was explicit wins. If the user picked a language on this device, that is a decision and
   * it is pushed. If they never did, the account's stored locale is the more considered value (it
   * may have been chosen on another device) and the app adopts it.
   */
  async reconcile(accountLocale: string): Promise<void> {
    if (!this.isSupported(accountLocale)) {
      return;
    }
    if (this.chosen) {
      if (accountLocale !== this.currentLocale) {
        try {
          await this.api.setLocalePreference(this.currentLocale);
        } catch {
          // Offline — the next explicit change re-syncs it.
        }
      }
      return;
    }
    if (accountLocale !== this.currentLocale) {
      await this.apply(accountLocale, false);
    }
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
