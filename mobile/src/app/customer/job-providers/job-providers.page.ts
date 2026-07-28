import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { Provider } from '../customer.models';
import { CustomerService } from '../customer.service';

/**
 * Providers matched to one open job (GET /jobs/{job}/providers) — the customer's shortlist. The
 * backend has already done the matching (skill + service-area coverage for on-site/hybrid, the whole
 * skilled pool for remote — P2-04) and ranked by tier then rating, so this screen just renders the
 * result. Tapping a provider opens their public profile carrying the job context, so the profile's
 * CTA can send a real direct offer (P2-05). Falls back to the demo list when offline.
 */
@Component({
  selector: 'app-job-providers',
  templateUrl: './job-providers.page.html',
  styleUrls: ['./job-providers.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class JobProvidersPage {
  private readonly customers = inject(CustomerService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  /** Public for the back-button href; the shortlist belongs to this job. */
  readonly jobId = this.route.snapshot.paramMap.get('id') ?? '';

  /** The demo shortlist shows instantly; the real matches replace it once loaded. */
  readonly providers = signal<Provider[]>(this.customers.listProviders('both'));
  readonly loading = signal(true);

  constructor() {
    void this.load();
  }

  private async load(): Promise<void> {
    const real = await this.customers.fetchJobProviders(this.jobId);
    if (real !== null) {
      this.providers.set(real);
    }
    this.loading.set(false);
  }

  /** Open the provider's public profile, carrying the job so its CTA can send a real offer. */
  openProfile(provider: Provider): void {
    void this.router.navigate(['/provider', provider.id], { queryParams: { job: this.jobId } });
  }
}
