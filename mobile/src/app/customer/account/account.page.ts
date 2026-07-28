import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { AuthService } from '../../core/auth.service';
import { Locale, LocaleService, SUPPORTED_LOCALES } from '../../core/locale.service';
import { ThemeChoice, ThemeService } from '../../core/theme.service';
import { CustomerService } from '../customer.service';

/**
 * Account — profile, saved addresses, language (FR/EN are both first-class, doc 09) and appearance.
 * Language and theme are owned by their services (persisted, applied on boot); this page just reflects
 * and sets them.
 */
@Component({
  selector: 'app-account',
  templateUrl: './account.page.html',
  styleUrls: ['./account.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class AccountPage {
  private readonly locales = inject(LocaleService);
  private readonly themes = inject(ThemeService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly customers = inject(CustomerService);

  readonly me = this.customers.me;
  readonly addresses = this.customers.addresses;
  readonly supported = SUPPORTED_LOCALES;
  readonly locale = signal<Locale>(this.locales.current);
  readonly theme = signal<ThemeChoice>(this.themes.current);

  offerServices(): void {
    void this.router.navigate(['/pro']);
  }

  async logout(): Promise<void> {
    await this.auth.logout();
    void this.router.navigate(['/welcome']);
  }

  async setLocale(locale: Locale): Promise<void> {
    await this.locales.choose(locale);
    this.locale.set(locale);
  }

  setTheme(choice: ThemeChoice): void {
    this.theme.set(choice);
    void this.themes.set(choice);
  }
}
