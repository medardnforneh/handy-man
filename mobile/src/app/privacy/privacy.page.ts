import { CommonModule } from '@angular/common';
import { Component, OnDestroy, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController, IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { Subscription } from 'rxjs';
import { AuthService } from '../core/auth.service';
import { RightsService } from '../core/rights.service';

/** One top-level part of the export, flattened into rows a person can actually read. */
export interface ExportSection {
  key: string;
  title: string;
  rows: { label: string; value: string }[];
  /** Repeated things (addresses, devices) are counted rather than listed one field at a time. */
  count: number | null;
  /** We hold nothing under this heading. Said out loud rather than dropped — see toSections. */
  empty: boolean;
}

/**
 * The data-subject rights (P1-10) — see everything held about you, and have it destroyed.
 *
 * Both operations existed on day one and neither was reachable from the app, which made them the
 * one gap on the uncalled list that is a legal obligation rather than a missing convenience.
 *
 * Outside both shells, like `/safety`: a provider has exactly the same rights as a customer, and
 * putting this in the customer section would have made it a customer feature.
 */
@Component({
  selector: 'app-privacy',
  templateUrl: './privacy.page.html',
  styleUrls: ['./privacy.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class PrivacyPage implements OnDestroy {
  private readonly rights = inject(RightsService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly alerts = inject(AlertController);
  private readonly toasts = inject(ToastController);
  private readonly i18n = inject(TranslateService);

  readonly sections = signal<ExportSection[] | null>(null);
  readonly loadFailed = signal(false);
  readonly delivering = signal(false);
  readonly erasing = signal(false);

  private payload: Record<string, unknown> | null = null;

  private readonly langChanges: Subscription;

  constructor() {
    void this.load();

    // These rows are labelled from the SERVER's own field names, so the naming is resolved here
    // rather than by a pipe in the template — and anything resolved imperatively freezes at the
    // language that was current at that moment. The account's language is adopted asynchronously
    // at launch, so on a cold load that moment is usually before it lands: the chrome came up
    // French and every label under it stayed English. Rebuild them whenever the language moves,
    // which also covers someone switching language with this screen open.
    this.langChanges = this.i18n.onLangChange.subscribe(() => {
      if (this.payload !== null) {
        this.sections.set(this.toSections(this.payload));
      }
    });
  }

  ngOnDestroy(): void {
    this.langChanges.unsubscribe();
  }

  /**
   * The export is fetched and SHOWN, not hidden behind a download button. Seeing what is held about
   * you is the right itself; a file you must first save and then find a JSON reader for is the
   * portability half of it, and that is what {@see save()} is for.
   */
  private async load(): Promise<void> {
    const data = await this.rights.export();
    this.payload = data;
    this.loadFailed.set(data === null);
    this.sections.set(data === null ? null : this.toSections(data));
  }

  private toSections(data: Record<string, unknown>): ExportSection[] {
    return Object.entries(data)
      .filter(([key]) => key !== 'exported_at')
      .map(([key, value]) => {
        const head = { key, title: this.titleFor(key) };

        // Nothing held under this heading — a customer who has never offered services has no
        // provider profile, and the server sends null for it. The heading stays and says so:
        // dropping it would leave the reader unable to tell "we hold none of this" from "we did
        // not show you this", which on a right-of-access screen are very different answers.
        if (value === null || (Array.isArray(value) && value.length === 0)) {
          return { ...head, rows: [], count: null, empty: true };
        }
        if (Array.isArray(value)) {
          return { ...head, rows: [], count: value.length, empty: false };
        }
        if (typeof value === 'object') {
          return {
            ...head,
            count: null,
            empty: false,
            rows: Object.entries(value as Record<string, unknown>)
              .filter(([, v]) => v !== null && v !== '')
              .map(([label, v]) => ({ label: this.labelFor(label), value: this.valueFor(label, String(v)) })),
          };
        }
        return {
          ...head,
          rows: [{ label: this.labelFor(key), value: String(value) }],
          count: null,
          empty: false,
        };
      });
  }

  /**
   * A stored CODE said as a word, for the handful of fields that hold one.
   *
   * `fr` and `active` are what the database keeps and neither is an answer to "what language do you
   * read?" or "what is my account doing?". Only these fields are translated: run it over every
   * value and a display name that happens to read `active` would be rewritten too.
   */
  private valueFor(field: string, raw: string): string {
    if (!['locale', 'comms_locale', 'status'].includes(field)) {
      return raw;
    }
    const key = `rights.v.${raw}`;
    const translated = this.i18n.instant(key);

    return translated === key ? raw : translated;
  }

  /** A section's name in the reader's language, falling back to the server's key — see labelFor. */
  private titleFor(section: string): string {
    const key = `rights.s.${section}`;
    const translated = this.i18n.instant(key);

    return translated === key ? section : translated;
  }

  /**
   * A field's name in the reader's language, falling back to the server's own key.
   *
   * The fallback is the point: the export is whatever the server holds, and a field added there
   * later must appear on this screen as `some_new_field` rather than be dropped or, worse, printed
   * as the untranslated key `rights.f.some_new_field`. An export that quietly omits a field is not
   * an export.
   */
  private labelFor(field: string): string {
    const key = `rights.f.${field}`;
    const translated = this.i18n.instant(key);

    return translated === key ? field : translated;
  }

  async save(): Promise<void> {
    if (this.payload === null || this.delivering()) {
      return;
    }
    this.delivering.set(true);
    const result = await this.rights.deliver(this.payload);
    this.delivering.set(false);

    // Named, not assumed: a browser saved a file and a packaged app put it on the clipboard, and
    // telling someone their data was saved when it was copied is the sort of lie this screen is for.
    await this.say(
      result === 'saved' ? 'rights.saved' : result === 'copied' ? 'rights.copied' : 'rights.save_failed',
      result !== 'failed',
    );
  }

  /**
   * Erasure, confirmed by TYPING the word rather than tapping a button.
   *
   * The panic button is held instead of tapped for the same reason in the opposite direction: the
   * cost of an accident is high and the action cannot be walked back. There is no undo endpoint
   * because there is no undo — the party's data key is destroyed, not archived.
   */
  async erase(): Promise<void> {
    const word = this.i18n.instant('rights.erase_word');
    const alert = await this.alerts.create({
      header: this.i18n.instant('rights.erase_title'),
      message: this.i18n.instant('rights.erase_confirm', { word }),
      inputs: [{ name: 'word', type: 'text', attributes: { autocapitalize: 'characters' } }],
      buttons: [
        { text: this.i18n.instant('common.cancel'), role: 'cancel' },
        { text: this.i18n.instant('rights.erase_yes'), role: 'confirm' },
      ],
    });
    await alert.present();

    const { role, data } = await alert.onDidDismiss<{ values: { word: string } }>();
    if (role !== 'confirm') {
      return;
    }
    if ((data?.values.word ?? '').trim().toLocaleUpperCase() !== word.toLocaleUpperCase()) {
      await this.say('rights.erase_mismatch', false);
      return;
    }

    this.erasing.set(true);
    const ok = await this.rights.erase();
    this.erasing.set(false);
    if (!ok) {
      await this.say('rights.erase_failed', false);
      return;
    }

    // The session is already dead server-side — every token was deleted with the rest of the PII.
    // Clearing it locally is what stops the app from sitting on a signed-in shell that 401s.
    await this.auth.logout();
    await this.say('rights.erased', true);
    void this.router.navigate(['/welcome']);
  }

  private async say(key: string, ok: boolean): Promise<void> {
    const toast = await this.toasts.create({
      message: this.i18n.instant(key),
      duration: ok ? 2600 : 4000,
      color: ok ? 'success' : 'danger',
      position: 'bottom',
    });
    await toast.present();
  }
}
