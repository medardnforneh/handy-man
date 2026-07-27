import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { AuthService } from '../../core/auth.service';
import { Locale, LocaleService, SUPPORTED_LOCALES } from '../../core/locale.service';
import { ProviderService } from '../provider.service';

type ThemeChoice = 'system' | 'light' | 'dark';

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
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class ProviderProfilePage {
  private readonly provider = inject(ProviderService);
  private readonly locales = inject(LocaleService);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly name = this.provider.name;
  readonly initials = this.provider.initials;
  readonly rating = this.provider.rating;
  readonly verificationTier = this.provider.verificationTier;
  readonly skills = this.provider.skills;
  readonly serviceArea = this.provider.serviceArea;

  readonly supported = SUPPORTED_LOCALES;
  readonly locale = signal<Locale>(this.locales.current);
  readonly theme = signal<ThemeChoice>('system');
  readonly available = signal(this.provider.isAvailable());

  /** Tier 2 (ID) or above is "identity verified" for on-site paid work (P6-03). */
  readonly fullyVerified = this.verificationTier >= 2;

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
    const root = document.documentElement;
    if (choice === 'system') {
      root.removeAttribute('data-theme');
    } else {
      root.setAttribute('data-theme', choice);
    }
  }

  backToCustomer(): void {
    void this.router.navigate(['/tabs/discover']);
  }

  async logout(): Promise<void> {
    await this.auth.logout();
    void this.router.navigate(['/welcome']);
  }
}
