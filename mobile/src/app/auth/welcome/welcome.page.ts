import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
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

  readonly supported = SUPPORTED_LOCALES;
  readonly countryCode = '+237'; // Cameroon; the only market at launch (doc 00)
  readonly locale = signal<Locale>(this.locales.current);
  readonly local = signal(''); // local digits, without the +237 prefix

  /** Cameroon subscriber numbers are 9 digits; only then can we send a code. */
  readonly canSend = computed(() => this.local().length === 9);

  setLocale(locale: Locale): void {
    void this.locales.choose(locale);
    this.locale.set(locale);
  }

  onPhone(value: string | null | undefined): void {
    this.local.set((value ?? '').replace(/\D/g, '').slice(0, 9));
  }

  async sendCode(): Promise<void> {
    if (!this.canSend()) {
      return;
    }
    await this.auth.requestOtp(`+237${this.local()}`);
    void this.router.navigate(['/verify']);
  }
}
