import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { MoneyPipe } from '../../customer/money.pipe';
import { Payout, PayoutStatus, ProviderWallet } from '../provider.models';
import { ProviderService } from '../provider.service';

/** Earnings tab — the payable balance the provider can withdraw and the history of payouts (P3-08). */
@Component({
  selector: 'app-provider-earnings',
  templateUrl: './earnings.page.html',
  styleUrls: ['./earnings.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe],
})
export class ProviderEarningsPage {
  private readonly provider = inject(ProviderService);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  /** Fixture wallet/history first (instant, offline-safe); the real earnings replace them once loaded. */
  readonly wallet = signal<ProviderWallet>(this.provider.getWallet());
  readonly payouts = signal<Payout[]>(this.provider.listPayouts());

  constructor() {
    void this.load();
  }

  private async load(): Promise<void> {
    const real = await this.provider.fetchEarnings();
    if (real !== null) {
      this.wallet.set(real.wallet);
      this.payouts.set(real.payouts);
    }
  }

  tone(status: PayoutStatus): string {
    switch (status) {
      case 'paid': return 'tone-success';
      case 'failed': return 'tone-danger';
      default: return 'tone-warning';
    }
  }

  async withdraw(): Promise<void> {
    const toast = await this.toasts.create({
      message: this.translate.instant('pro.payout_requested'),
      duration: 2000, position: 'top', color: 'success',
    });
    await toast.present();
  }
}
