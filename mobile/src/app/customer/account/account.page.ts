import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { AuthService } from '../../core/auth.service';
import { Locale, LocaleService, SUPPORTED_LOCALES } from '../../core/locale.service';
import { ThemeChoice, ThemeService } from '../../core/theme.service';
import { EmptyStateComponent } from '../../core/ui/empty-state.component';
import { ProviderService } from '../../provider/provider.service';
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
  imports: [CommonModule, IonicModule, TranslatePipe, EmptyStateComponent],
})
export class AccountPage {
  private readonly locales = inject(LocaleService);
  private readonly themes = inject(ThemeService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly customers = inject(CustomerService);
  private readonly providers = inject(ProviderService);

  readonly me = this.customers.me;
  readonly addresses = this.customers.addresses;

  /**
   * The heading of your own account page. A display name if the server sent one, otherwise the
   * phone you signed in with — which is the identity in this product (doc 02 is phone-primary), and
   * is always true. Only when neither has loaded does it say so, rather than filling the line in.
   */
  readonly displayName = computed(() => this.me().name || this.me().phone);
  readonly knowsName = computed(() => this.me().name !== '' && this.me().phone !== '');
  readonly supported = SUPPORTED_LOCALES;
  readonly locale = signal<Locale>(this.locales.current);
  readonly theme = signal<ThemeChoice>(this.themes.current);

  /** Re-read on entry so an address saved on the next screen is here when you come back. */
  ionViewWillEnter(): void {
    void this.customers.loadAddresses();
  }

  addAddress(): void {
    void this.router.navigate(['/new-address']);
  }

  /**
   * "Offer services". Someone who already has a provider profile wants their dashboard; someone
   * who does not wants the signup — landing them on a dashboard of zeroes with no way out of it is
   * what this row used to do for every customer who tapped it.
   */
  /** The panic alert and the contacts it reaches (P6-04). */
  openSafety(): void {
    void this.router.navigate(['/safety']);
  }

  async offerServices(): Promise<void> {
    const profile = await this.providers.fetchProfile();
    void this.router.navigate([profile === null ? '/become-a-provider' : '/pro']);
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
