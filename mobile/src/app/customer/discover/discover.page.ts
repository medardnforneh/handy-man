import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { EngagementMode, Provider } from '../customer.models';
import { CustomerService } from '../customer.service';

type ModeFilter = EngagementMode | 'both';

/**
 * Discover — the customer's entry point: search, an on-site/remote filter (geography is conditional,
 * doc 06), categories, and nearby providers. Requesting a quote is one tap from here.
 */
@Component({
  selector: 'app-discover',
  templateUrl: './discover.page.html',
  styleUrls: ['./discover.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class DiscoverPage {
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);

  /** Signed-in customer's initials for the avatar — real when a session is present (GET /auth/me). */
  readonly userInitials = this.customers.me;

  readonly categories = this.customers.categories;
  readonly mode = signal<ModeFilter>('onsite');
  /** Demo cards until the real rail loads (and when there is no network) — never mixed. */
  readonly providers = signal<Provider[]>(this.customers.listProviders('onsite'));

  constructor() {
    void this.load();
  }

  setMode(mode: ModeFilter): void {
    this.mode.set(mode);
    // Show the matching fixtures immediately so the segment feels instant, then replace them with
    // the real pool. On a good connection the swap is invisible; on a bad one the screen still moved.
    this.providers.set(this.customers.listProviders(mode));
    void this.load();
  }

  /**
   * Load the real rail. This endpoint is public, so it works before sign-in — the one screen where
   * that matters most, since it is what a new customer looks at to decide whether to bother.
   */
  private async load(): Promise<void> {
    const mode = this.mode();
    const real = await this.customers.fetchProviders(mode);
    // Ignore a response that lost the race with a faster tap on another segment.
    if (real !== null && this.mode() === mode) {
      this.providers.set(real);
    }
  }

  openProfile(provider: Provider): void {
    // Tapping a provider opens their public profile (reviews + metrics) before requesting a quote.
    void this.router.navigate(['/provider', provider.id]);
  }

  openWorkspace(provider: Provider): void {
    // The quick "Quote" shortcut opens the engagement workspace for the conversation it creates.
    void this.router.navigate(['/workspace', provider.id]);
  }
}
