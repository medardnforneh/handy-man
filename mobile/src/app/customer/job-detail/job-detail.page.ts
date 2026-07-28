import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { JobDetail, JobStatus, MilestoneStatus } from '../customer.models';
import { CustomerService } from '../customer.service';
import { MoneyPipe } from '../money.pipe';

/**
 * Job detail — the overview a customer sees for one job, distinct from the chat workspace: the money
 * held in escrow, the milestone plan, the assigned provider, and the location. Approving a submitted
 * milestone releases its escrow slice (mirrors ApproveMilestone / P3-10). "Open chat" hands off to
 * the workspace where the engagement plays out as a thread.
 */
@Component({
  selector: 'app-job-detail',
  templateUrl: './job-detail.page.html',
  styleUrls: ['./job-detail.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe],
})
export class JobDetailPage {
  private readonly customers = inject(CustomerService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  private readonly id = this.route.snapshot.paramMap.get('id') ?? '';
  readonly job = signal<JobDetail>(this.customers.jobDetail(this.id));

  constructor() {
    // Show the fixture instantly, then swap in the real job (GET /jobs/{id}) if reachable.
    void this.customers.fetchJobDetail(this.id).then((real) => {
      if (real !== null) {
        this.job.set(real);
      }
    });
  }

  tone(status: JobStatus): string {
    switch (status) {
      case 'completed': return 'tone-success';
      case 'in_progress': case 'work_submitted': return 'tone-warning';
      case 'cancelled': return 'tone-danger';
      case 'open': case 'draft': return 'tone-neutral';
      default: return 'tone-info';
    }
  }

  milestoneTone(status: MilestoneStatus): string {
    switch (status) {
      case 'paid': return 'tone-success';
      case 'submitted': return 'tone-warning';
      case 'in_progress': return 'tone-info';
      default: return 'tone-neutral';
    }
  }

  async approve(milestoneId: string): Promise<void> {
    this.job.set(await this.customers.approveAndRefresh(this.id, milestoneId));
    const toast = await this.toasts.create({
      message: this.translate.instant('jobdetail.released_toast'),
      duration: 2000, position: 'top', color: 'success',
    });
    await toast.present();
  }

  openChat(): void {
    void this.router.navigate(['/workspace', this.id]);
  }

  openProvider(): void {
    const providerId = this.job().providerId;
    if (providerId) {
      void this.router.navigate(['/provider', providerId]);
    }
  }
}
