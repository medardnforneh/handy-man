import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { MoneyPipe } from '../../customer/money.pipe';
import { JobStatus } from '../../customer/customer.models';
import { ActiveWork, Lead } from '../provider.models';
import { ProviderService } from '../provider.service';

/**
 * Provider dashboard — the "Offer services" landing. The provider's business at a glance: the money
 * they can withdraw (payable balance, P3-08), reputation + workload stats (rating/on-time hidden
 * below the P6-12 floor), the open opportunities they can quote, and the work in progress.
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

  readonly name = this.provider.name;
  readonly initials = this.provider.initials;
  readonly verified = this.provider.verified;
  readonly wallet = this.provider.getWallet();
  readonly stats = this.provider.getStats();
  readonly leads = signal<Lead[]>(this.provider.listLeads());
  readonly active = this.provider.listActive();

  readonly hasOnTime = computed(() => this.stats.onTimeRate !== null);

  onTimePercent(): number {
    return Math.round((this.stats.onTimeRate ?? 0) * 100);
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
    void this.router.navigate(['/pro/lead', lead.id]);
  }

  openWork(work: ActiveWork): void {
    void this.router.navigate(['/workspace', work.id]);
  }

  async withdraw(): Promise<void> {
    const toast = await this.toasts.create({
      message: this.translate.instant('pro.payout_requested'),
      duration: 2000, position: 'top', color: 'success',
    });
    await toast.present();
  }
}
