import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { MoneyPipe } from '../../customer/money.pipe';
import { JobStatus } from '../../customer/customer.models';
import { ActiveWork, Lead, ProviderStats, ProviderWallet } from '../provider.models';
import { ProviderService } from '../provider.service';

/**
 * Provider dashboard — the "Offer services" landing. The provider's business at a glance: the money
 * they can withdraw (payable balance, P3-08), reputation + workload stats, the open opportunities
 * they can quote, and the work in progress.
 *
 * Everything here is COMPOSED from endpoints that already exist — earnings, the work list, and the
 * provider's own public metrics — rather than a bespoke dashboard endpoint. Rating and on-time come
 * back null below the P6-12 sample floor and are rendered as "building", never as a flattering
 * small-sample number. Each panel falls back to its fixture so the screen stays whole offline.
 */
@Component({
  selector: 'app-provider-home',
  templateUrl: './home.page.html',
  styleUrls: ['./home.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe],
})
export class ProviderHomePage {
  private readonly provider = inject(ProviderService);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  /** Who the provider is — a signal on the service, so the real profile replaces the fixture live. */
  readonly identity = this.provider.identity;

  readonly wallet = signal<ProviderWallet>(this.provider.getWallet());
  readonly stats = signal<ProviderStats>(this.provider.getStats());
  readonly leads = signal<Lead[]>(this.provider.listLeads());
  readonly active = signal<ActiveWork[]>(this.provider.listActive());

  /** The dashboard shows a couple of each; the section headers link to the full lists. */
  readonly topLeads = computed(() => this.leads().slice(0, 2));
  readonly topActive = computed(() => this.active().slice(0, 2));
  readonly leadCount = computed(() => this.leads().length);

  readonly hasOnTime = computed(() => this.stats().onTimeRate !== null);
  readonly onTimePercent = computed(() => Math.round((this.stats().onTimeRate ?? 0) * 100));

  constructor() {
    void this.load();
  }

  /**
   * Load the panels independently, so one slow or unavailable read never blanks the others — each
   * keeps its fixture when its own call fails.
   */
  private async load(): Promise<void> {
    const [, earnings, leads, active, stats] = await Promise.all([
      this.provider.fetchProfile(),
      this.provider.fetchEarnings(),
      this.provider.fetchOpportunities(),
      this.provider.fetchActive(),
      this.provider.fetchStats(),
    ]);

    if (earnings) {
      this.wallet.set(earnings.wallet);
    }
    if (leads) {
      this.leads.set(leads);
    }
    if (active) {
      this.active.set(active);
    }
    if (stats) {
      this.stats.set(stats);
    }
  }

  tone(status: JobStatus): string {
    switch (status) {
      case 'completed': return 'tone-success';
      case 'in_progress': case 'work_submitted': return 'tone-warning';
      case 'cancelled': return 'tone-danger';
      default: return 'tone-info';
    }
  }

  openLead(lead: Lead): void {
    void this.router.navigate(['/opportunity', lead.id]);
  }

  openWork(work: ActiveWork): void {
    void this.router.navigate(['/work', work.id]);
  }

  /** The client book (P7-08) — history, lifetime value, and the manual re-engagement nudge. */
  openClients(): void {
    void this.router.navigate(['/clients']);
  }

  seeOpportunities(): void {
    void this.router.navigate(['/pro/opportunities']);
  }

  seeWork(): void {
    void this.router.navigate(['/pro/work']);
  }

  seeEarnings(): void {
    void this.router.navigate(['/pro/earnings']);
  }

  /**
   * Withdrawing is a real money movement (P3-08) with its own screen — the dashboard's job is to
   * take you there, not to fire a payout from a tile.
   */
  async withdraw(): Promise<void> {
    if (this.wallet().availableMinor <= 0) {
      const toast = await this.toasts.create({
        message: this.translate.instant('pro.nothing_to_withdraw'),
        duration: 2000, position: 'top', color: 'medium',
      });
      await toast.present();
      return;
    }
    this.seeEarnings();
  }
}
