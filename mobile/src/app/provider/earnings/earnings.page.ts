import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { EmptyStateComponent } from '../../core/ui/empty-state.component';
import { CustomerService } from '../../customer/customer.service';
import { MoneyPipe } from '../../customer/money.pipe';
import { Payout, PayoutStatus, ProviderWallet } from '../provider.models';
import { ProviderService } from '../provider.service';

/**
 * Earnings tab — the payable balance the provider can withdraw and the history of payouts (P3-08).
 *
 * Withdrawing used to show a success toast and request nothing at all: the most dishonest thing a
 * money screen can do, and invisible from the app alone because the history beneath it was fixture
 * data that already looked populated. It posts a real payout now, and the sheet asks the two things
 * the endpoint needs — how much, and the wallet it lands in.
 */
@Component({
  selector: 'app-provider-earnings',
  templateUrl: './earnings.page.html',
  styleUrls: ['./earnings.page.scss'],
  imports: [CommonModule, FormsModule, IonicModule, TranslatePipe, MoneyPipe, EmptyStateComponent],
})
export class ProviderEarningsPage {
  private readonly provider = inject(ProviderService);
  private readonly customers = inject(CustomerService);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  /** Fixture wallet/history first (instant, offline-safe); the real earnings replace them once loaded. */
  readonly wallet = signal<ProviderWallet>(this.provider.getWallet());
  readonly payouts = signal<Payout[]>(this.provider.listPayouts());

  /**
   * Prepaid lead credits (P2-08), out of the same earnings payload. Null until it is read, and the
   * row stays hidden rather than showing a zero — a provider who has bought credits should never
   * be told they have none because a request failed.
   */
  readonly credits = signal<number | null>(null);

  // --- the withdraw sheet -------------------------------------------------------------------------

  readonly sheetOpen = signal(false);
  readonly amount = signal(0);
  readonly msisdn = signal('');
  readonly touched = signal(false);
  readonly busy = signal(false);

  readonly amountMissing = computed(() => this.touched() && this.amount() <= 0);
  readonly amountTooBig = computed(() => this.touched() && this.amount() > this.wallet().availableMinor);
  readonly msisdnMissing = computed(() => this.touched() && this.msisdn().trim() === '');

  constructor() {
    void this.load();
  }

  private async load(): Promise<void> {
    const real = await this.provider.fetchEarnings();
    if (real !== null) {
      this.wallet.set(real.wallet);
      this.payouts.set(real.payouts);
      this.credits.set(real.leadCreditsMinor);
    }
  }

  tone(status: PayoutStatus): string {
    switch (status) {
      case 'paid': return 'tone-success';
      case 'failed': return 'tone-danger';
      default: return 'tone-warning';
    }
  }

  /** Open the sheet pre-filled with everything: the whole balance, to the account's own number. */
  openSheet(): void {
    this.amount.set(this.wallet().availableMinor);
    this.msisdn.set(this.customers.me().phone);
    this.touched.set(false);
    this.sheetOpen.set(true);
  }

  closeSheet(): void {
    this.sheetOpen.set(false);
  }

  /** "All of it" — the common case, and the one people mistype most. */
  takeAll(): void {
    this.amount.set(this.wallet().availableMinor);
  }

  onAmount(value: string | number | null | undefined): void {
    // A cleared field is 0, not NaN: NaN would slip past `<= 0` and be sent to the server.
    const parsed = Math.floor(Number(value));
    this.amount.set(Number.isFinite(parsed) ? Math.max(0, parsed) : 0);
  }

  async submit(): Promise<void> {
    this.touched.set(true);
    if (this.amountMissing() || this.amountTooBig() || this.msisdnMissing()) {
      return;
    }

    this.busy.set(true);
    const result = await this.provider.requestPayout(this.amount(), this.msisdn().trim());
    this.busy.set(false);

    if (result.ok) {
      this.sheetOpen.set(false);
      // Reload rather than patch: the payout reserves funds, so BOTH the available balance and the
      // pending figure move, and the server's arithmetic is the only one that counts here.
      await this.load();
    }

    const toast = await this.toasts.create({
      message: result.ok
        ? this.translate.instant('pro.payout_requested')
        : result.detail ?? this.translate.instant('wd.failed'),
      duration: result.ok ? 2500 : 4000,
      position: 'top',
      color: result.ok ? 'success' : 'danger',
    });
    await toast.present();
  }
}
