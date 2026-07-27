import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { MoneyPipe } from '../../customer/money.pipe';
import { Lead } from '../provider.models';
import { ProviderService } from '../provider.service';

/** Opportunities tab — the full feed of open requests a provider can quote. */
@Component({
  selector: 'app-provider-opportunities',
  templateUrl: './opportunities.page.html',
  styleUrls: ['./opportunities.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe],
})
export class ProviderOpportunitiesPage {
  private readonly provider = inject(ProviderService);
  private readonly router = inject(Router);

  readonly leads = signal<Lead[]>(this.provider.listLeads());

  openLead(lead: Lead): void {
    void this.router.navigate(['/opportunity', lead.id]);
  }
}
