import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
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
  private readonly toasts = inject(ToastController);
  private readonly i18n = inject(TranslateService);

  readonly sending = signal(false);

  private readonly partyId = this.route.snapshot.paramMap.get('id') ?? '';
  /** The job that led here — present when arriving from a job's provider search; enables the real fetch. */
  private readonly jobId = this.route.snapshot.queryParamMap.get('job');

  /** Fixture profile first (instant, offline-safe); the real public profile replaces it once loaded. */
  readonly provider = signal(this.customers.provider(this.partyId));

  constructor() {
    void this.loadReal();
  }

  /**
   * The real profile, keyed by party alone. This used to bail out entirely without a job in the
   * query string — so arriving from the discover rail showed demo data — because a headline could
   * only be read out of a job's match list. `GET /providers/{party}` removed that dependency.
   */
  private async loadReal(): Promise<void> {
    const real = await this.customers.fetchProviderProfile(this.partyId);
    if (real !== null) {
      this.provider.set(real);
    }
  }

  /** Below the P6-12 sample floor we have too little signal to display an on-time rate. */
  readonly hasEnoughSignal = computed(() => this.provider().onTimeRate !== null);

  /** A real job context turns the CTA into "send a direct offer" rather than the demo workspace open. */
  readonly hasJob = this.jobId !== null;

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
    return Math.round((this.provider().onTimeRate ?? 0) * 100);
  }

  /**
   * With a real job context this sends a direct offer to the provider (POST /jobs/{job}/offers) and
   * returns the customer to the job; without one (the demo entry from Discover) it opens the workspace
   * so the fixture conversation flow still works.
   */
  async requestQuote(): Promise<void> {
    if (this.jobId === null) {
      void this.router.navigate(['/workspace', this.provider().id]);
      return;
    }
    if (this.sending()) {
      return;
    }
    this.sending.set(true);
    const ok = await this.customers.sendOffer(this.jobId, this.partyId);
    this.sending.set(false);
    await this.toast(ok ? 'provider.offer_sent' : 'provider.offer_failed', ok ? 'success' : 'danger');
    if (ok) {
      void this.router.navigate(['/job', this.jobId]);
    }
  }

  private async toast(key: string, color: 'success' | 'danger'): Promise<void> {
    const t = await this.toasts.create({
      message: this.i18n.instant(key), duration: 2600, position: 'top', color,
    });
    await t.present();
  }
}
