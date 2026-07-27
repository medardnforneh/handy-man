import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { MoneyPipe } from '../../customer/money.pipe';
import { Lead } from '../provider.models';
import { ProviderService } from '../provider.service';

/**
 * Lead detail + quote composer — how a provider responds to an opportunity (mirrors SubmitQuotation).
 * The provider reads the request, sets a price (and optional deposit + message), and sends. The quote
 * can only be sent once a positive price is set, so an empty quote never reaches the server. A lead
 * that has since been taken/withdrawn shows an unavailable state rather than a broken form.
 */
@Component({
  selector: 'app-provider-lead',
  templateUrl: './lead.page.html',
  styleUrls: ['./lead.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe],
})
export class ProviderLeadPage {
  private readonly provider = inject(ProviderService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  private readonly id = this.route.snapshot.paramMap.get('id') ?? '';
  readonly lead = signal<Lead | null>(this.provider.lead(this.id));

  readonly price = signal('');
  readonly deposit = signal('');
  readonly message = signal('');

  readonly canSend = computed(() => Number(this.price()) > 0);

  onPrice(value: string | null | undefined): void {
    this.price.set((value ?? '').replace(/\D/g, ''));
  }

  onDeposit(value: string | null | undefined): void {
    this.deposit.set((value ?? '').replace(/\D/g, ''));
  }

  onMessage(value: string | null | undefined): void {
    this.message.set(value ?? '');
  }

  async send(): Promise<void> {
    if (!this.canSend()) {
      return;
    }
    this.provider.submitQuote(this.id);
    const toast = await this.toasts.create({
      message: this.translate.instant('pro.quote_sent'),
      duration: 2000, position: 'top', color: 'success',
    });
    await toast.present();
    void this.router.navigate(['/pro']);
  }
}
