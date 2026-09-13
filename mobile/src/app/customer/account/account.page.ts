import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { AlertController, IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
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
  private readonly alerts = inject(AlertController);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  readonly me = this.customers.me;
  readonly addresses = this.customers.addresses;
  /** What this account has done here — the three figures under the name (handoff: You). */
  readonly jobsDone = computed(() => this.customers.jobs().filter((j) => j.status === 'completed').length);
  readonly jobsActive = computed(() => this.customers.jobs().filter((j) => j.status !== 'completed' && j.status !== 'cancelled').length);

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

  openPrivacy(): void {
    void this.router.navigate(['/privacy']);
  }

  async offerServices(): Promise<void> {
    const profile = await this.providers.fetchProfile();
    void this.router.navigate([profile === null ? '/become-a-provider' : '/pro']);
  }

  async logout(): Promise<void> {
    await this.auth.logout();
    void this.router.navigate(['/welcome']);
  }

  // --- Referrals (P8-01) -------------------------------------------------------------------------
  //
  // Two halves of the same feature and both were unreachable: the code a person shares, and the box
  // where they enter somebody else's. Word of mouth is how this product actually spreads — a
  // plumber recommended by a neighbour — so the mechanism that rewards it should not be the one
  // thing built and hidden.

  readonly referralCode = signal<string | null>(null);

  /** Read on demand rather than at page load: most visits here are for an address or the language. */
  async showReferral(): Promise<void> {
    if (this.referralCode() === null) {
      this.referralCode.set(await this.customers.referralCode());
    }
  }

  /** Copy, never send. Who gets a referral code is the whole point of having one. */
  async copyReferral(): Promise<void> {
    const code = this.referralCode();
    if (code === null) {
      return;
    }

    try {
      await navigator.clipboard.writeText(code);
      await this.toast('referral.copied', 'success');
    } catch {
      // The code is on screen in full, so this is an inconvenience rather than a dead end.
      await this.toast('referral.copy_failed', 'danger');
    }
  }

  async claimReferral(): Promise<void> {
    const alert = await this.alerts.create({
      header: this.translate.instant('referral.claim_title'),
      message: this.translate.instant('referral.claim_body'),
      inputs: [{
        name: 'code',
        type: 'text',
        placeholder: this.translate.instant('referral.claim_ph'),
        attributes: { maxlength: 32, autocapitalize: 'characters' },
      }],
      buttons: [
        { text: this.translate.instant('common.cancel'), role: 'cancel' },
        { text: this.translate.instant('referral.claim_send'), role: 'confirm' },
      ],
    });
    await alert.present();

    const { role, data } = await alert.onDidDismiss<{ values?: { code?: string } }>();
    const code = (data?.values?.code ?? '').trim();
    if (role !== 'confirm' || code === '') {
      return;
    }

    const result = await this.customers.claimReferral(code);
    if (result.ok) {
      await this.toast('referral.claimed', 'success');
      return;
    }
    // Every refusal here is a different real thing — not a code, your own code, already referred —
    // and the server says which. Ours would only be vaguer.
    await this.toast(result.detail ?? this.translate.instant('referral.claim_failed'), 'danger', result.detail !== undefined);
  }

  private async toast(key: string, color: 'success' | 'danger', raw = false): Promise<void> {
    const toast = await this.toasts.create({
      message: raw ? key : this.translate.instant(key),
      duration: color === 'success' ? 2500 : 4000,
      position: 'top',
      color,
    });
    await toast.present();
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
