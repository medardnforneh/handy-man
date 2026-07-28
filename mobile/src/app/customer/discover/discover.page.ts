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
  readonly providers = signal<Provider[]>(this.customers.listProviders('onsite'));

  setMode(mode: ModeFilter): void {
    this.mode.set(mode);
    this.providers.set(this.customers.listProviders(mode));
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
