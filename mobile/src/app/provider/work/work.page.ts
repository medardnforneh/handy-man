import { CommonModule } from '@angular/common';
import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { JobStatus } from '../../customer/customer.models';
import { ActiveWork } from '../provider.models';
import { ProviderService } from '../provider.service';

/** Work tab — the jobs a provider is actively delivering; each opens its workspace thread. */
@Component({
  selector: 'app-provider-work',
  templateUrl: './work.page.html',
  styleUrls: ['./work.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe],
})
export class ProviderWorkPage {
  private readonly provider = inject(ProviderService);
  private readonly router = inject(Router);

  readonly active = this.provider.listActive();

  tone(status: JobStatus): string {
    switch (status) {
      case 'completed': return 'tone-success';
      case 'in_progress': case 'work_submitted': return 'tone-warning';
      case 'cancelled': return 'tone-danger';
      default: return 'tone-info';
    }
  }

  open(work: ActiveWork): void {
    void this.router.navigate(['/work', work.id]);
  }
}
