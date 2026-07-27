import { CommonModule } from '@angular/common';
import { Component, computed, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { ProviderProfile } from '../customer.models';
import { CustomerService } from '../customer.service';

/**
 * The public provider profile — the discovery funnel's middle step: a customer taps a provider, sees
 * their reputation, then requests a quote. It mirrors `GET /v1/providers/{party}/reviews` + `/metrics`
 * and honours the two reputation disciplines from the backend:
 *   • the display rating is Bayesian-shrunk (P6-09) and null when unrated — never a bare prior;
 *   • the on-time rate is null below the sample-size floor (P6-12) — "100% (1 job)" is never shown.
 * Reviews are published double-blind results (P6-08); private notes never reach the client.
 */
@Component({
  selector: 'app-provider',
  templateUrl: './provider.page.html',
  styleUrls: ['./provider.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class ProviderPage {
  private readonly customers = inject(CustomerService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  readonly provider: ProviderProfile =
    this.customers.provider(this.route.snapshot.paramMap.get('id') ?? '');

  /** Below the P6-12 sample floor we have too little signal to display an on-time rate. */
  readonly hasEnoughSignal = computed(() => this.provider.onTimeRate !== null);

  /** Full + half + empty star glyphs for a rating out of 5 (display only). */
  stars(rating: number | null): ('full' | 'half' | 'empty')[] {
    const r = rating ?? 0;
    return Array.from({ length: 5 }, (_, i) => {
      if (r >= i + 1) return 'full';
      if (r >= i + 0.5) return 'half';
      return 'empty';
    });
  }

  onTimePercent(): number {
    return Math.round((this.provider.onTimeRate ?? 0) * 100);
  }

  requestQuote(): void {
    // Requesting a quote opens the engagement workspace for the conversation it creates.
    void this.router.navigate(['/workspace', this.provider.id]);
  }
}
