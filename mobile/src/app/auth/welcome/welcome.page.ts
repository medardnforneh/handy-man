import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { AuthService } from '../../core/auth.service';
import { Locale, LocaleService, SUPPORTED_LOCALES } from '../../core/locale.service';

/**
 * Welcome — the app's front door (doc 09: the language is OFFERED here on first launch, never forced;
 * doc 02: phone-primary, OTP-first, no password). The user picks a language, enters a Cameroon phone
 * number, and we start the OTP challenge. Country code is fixed to +237; only the local digits are
 * entered, and the button unlocks once they look like a real number.
 */
@Component({
  selector: 'app-welcome',
  templateUrl: './welcome.page.html',
  styleUrls: ['./welcome.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class WelcomePage {
  private readonly auth = inject(AuthService);
  private readonly locales = inject(LocaleService);
  private readonly router = inject(Router);
  private readonly i18n = inject(TranslateService);

  readonly supported = SUPPORTED_LOCALES;
  readonly countryCode = '+237'; // Cameroon; the only market at launch (doc 00)
  readonly locale = signal<Locale>(this.locales.current);
  readonly local = signal(''); // local digits, without the +237 prefix

  /** Cameroon subscriber numbers are 9 digits; only then can we send a code. */
  readonly canSend = computed(() => this.local().length === 9);

  readonly sending = signal(false);
  /** The server's own reason for refusing, shown in place rather than swallowed. */
  readonly error = signal('');

  setLocale(locale: Locale): void {
    void this.locales.choose(locale);
    this.locale.set(locale);
  }

  onPhone(value: string | null | undefined): void {
    this.local.set((value ?? '').replace(/\D/g, '').slice(0, 9));
  }

  /**
   * Ask for a code, and only move on if one is actually coming.
   *
   * A refusal (rate limit, rejected number) used to be discarded, so the user was sent to the
   * code-entry screen to wait for a code the server had declined to send — they would sit there
   * until the resend timer expired with no idea why. Now the server's own reason is shown and the
   * screen stays put. An unreachable backend still proceeds: that is the deliberate offline path.
   */
  async sendCode(): Promise<void> {
    if (!this.canSend() || this.sending()) {
      return;
    }
    this.sending.set(true);
    this.error.set('');

    const { outcome, detail } = await this.auth.requestOtp(`+237${this.local()}`);
    this.sending.set(false);

    if (outcome === 'refused') {
      this.error.set(detail ?? this.i18n.instant('errors.generic'));
      return;
    }
    void this.router.navigate(['/verify']);
  }
}
