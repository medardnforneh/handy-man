import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { AuthService } from '../../core/auth.service';
import { Locale, LocaleService, SUPPORTED_LOCALES } from '../../core/locale.service';
import { ThemeChoice, ThemeService } from '../../core/theme.service';
import { EmptyStateComponent } from '../../core/ui/empty-state.component';
import { CustomerService } from '../../customer/customer.service';
import { ProviderService } from '../provider.service';

/**
 * Provider profile — the "you" tab: how the provider appears to customers, their verification tier
 * (P6-03, which gates the paid jobs they can accept), an availability switch, listed skills and
 * service area, and shared preferences. "Verify your identity" is surfaced when the tier is below
 * full ID (the real upload flow is P6-01). Log out and hopping back to the customer app live here.
 */
@Component({
  selector: 'app-provider-profile',
  templateUrl: './profile.page.html',
  styleUrls: ['./profile.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, EmptyStateComponent],
})
export class ProviderProfilePage {
  private readonly provider = inject(ProviderService);
  private readonly locales = inject(LocaleService);
  private readonly themes = inject(ThemeService);
  private readonly auth = inject(AuthService);
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);

  /** Who the provider is — the service's signal, so the real profile replaces the fixture live. */
  readonly identity = this.provider.identity;

  /** Whether this user has a provider profile at all. Null party id means they have never made one. */
  readonly hasProfile = computed(() => this.identity().partyId !== null);

  /**
   * The name and initials to show at the top.
   *
   * A provider profile has a display name of its own; before there is one, the person is still
   * known — they are signed in — so this falls back to their account name rather than to a blank
   * avatar over the verification badge, which is what a user without a profile used to see. That
   * is not inventing a provider: it is naming the human who is looking at the screen.
   */
  readonly who = computed(() => {
    const profile = this.identity();
    const account = this.customers.me();
    return {
      name: profile.name || account.name || account.phone,
      initials: profile.initials || account.initials,
    };
  });

  readonly supported = SUPPORTED_LOCALES;
  readonly locale = signal<Locale>(this.locales.current);
  readonly theme = signal<ThemeChoice>(this.themes.current);
  readonly available = signal(this.provider.isAvailable());

  /** Tier 2 (ID) or above is "identity verified" for on-site paid work (P6-03). */
  readonly fullyVerified = computed(() => this.identity().verificationTier >= 2);

  /** Shown as "—" rather than a bare prior when there aren't enough reviews yet (P6-09/P6-12). */
  readonly hasRating = computed(() => this.identity().rating !== null);

  constructor() {
    // The profile may already be loaded (Home fetches it too); this just makes the tab self-sufficient.
    void this.provider.fetchProfile();
  }

  toggleAvailability(value: boolean): void {
    this.available.set(value);
    this.provider.setAvailable(value);
  }

  setLocale(locale: Locale): void {
    void this.locales.choose(locale);
    this.locale.set(locale);
  }

  setTheme(choice: ThemeChoice): void {
    this.theme.set(choice);
    void this.themes.set(choice);
  }

  /** Become a provider (P1-08) — the flow that has to happen before any of this section applies. */
  startOnboarding(): void {
    void this.router.navigate(['/become-a-provider']);
  }

  /**
   * Sending verification papers in (P6-01) — what this screen's "Verify" button has always claimed
   * to do. It was an inert button until now, on the one gate that decides whether a provider can be
   * paid for work at a customer's home.
   */
  openVerification(): void {
    void this.router.navigate(['/verification']);
  }

  /** The client book (P7-08) — a periodic review surface, so it lives here rather than in the tabs. */
  openClients(): void {
    void this.router.navigate(['/clients']);
  }

  backToCustomer(): void {
    void this.router.navigate(['/tabs/discover']);
  }

  async logout(): Promise<void> {
    await this.auth.logout();
    void this.router.navigate(['/welcome']);
  }
}
