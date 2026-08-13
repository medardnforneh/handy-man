import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { OfflineStripComponent } from '../../core/offline/offline-strip.component';
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
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe, OfflineStripComponent],
})
export class JobDetailPage {
  private readonly customers = inject(CustomerService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  private readonly id = this.route.snapshot.paramMap.get('id') ?? '';
  readonly job = signal<JobDetail>(this.customers.jobDetail(this.id));

  readonly busy = signal(false);
  readonly rating = signal(0);
  readonly reviewBody = signal('');
  readonly starValues = [1, 2, 3, 4, 5];

  /**
   * Marking the work finished is offered once there is a real engagement and nobody has done it
   * yet. It is deliberately not gated on a particular job status: a customer knows when the work
   * is done, and the server is idempotent about being told twice.
   */
  readonly canComplete = computed(() => {
    const job = this.job();
    return job.engagementId !== null && job.completedAt === null;
  });

  /** Reviewing opens when the work is finished, and closes once this person has had their say. */
  readonly canReview = computed(() => {
    const job = this.job();
    return job.engagementId !== null && job.completedAt !== null && !job.reviewed;
  });

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

  /**
   * Approve a milestone — releases its escrow slice. Offline this is queued like any other write,
   * but the toast tells the truth about which happened: "released" only once the server has said so,
   * because escrow is the customer's money and a premature confirmation is not a cosmetic error.
   */
  async approve(milestoneId: string): Promise<void> {
    const { outcome, job } = await this.customers.approveAndRefresh(this.id, milestoneId);
    if (job !== null) {
      this.job.set(job);
    }
    const { key, color } = {
      sent: { key: 'jobdetail.released_toast', color: 'success' },
      queued: { key: 'offline.queued_action', color: 'warning' },
      failed: { key: 'errors.generic', color: 'danger' },
    }[outcome];

    const toast = await this.toasts.create({
      message: this.translate.instant(key),
      duration: outcome === 'sent' ? 2000 : 3000,
      position: 'top',
      color,
    });
    await toast.present();
  }

  onReviewBody(value: string | null | undefined): void {
    this.reviewBody.set(value ?? '');
  }

  /**
   * Mark the engagement finished (P7-02). This is the event the rest of the lifecycle hangs off —
   * it opens the review window, schedules the review nudges and qualifies a referral — so the
   * screen re-reads the job afterwards rather than guessing at the new state.
   */
  async complete(): Promise<void> {
    const engagementId = this.job().engagementId;
    if (engagementId === null || this.busy()) {
      return;
    }
    this.busy.set(true);
    const ok = await this.customers.completeEngagement(engagementId);
    await this.refresh();
    this.busy.set(false);
    await this.toast(ok ? 'jobdetail.complete_done' : 'errors.generic', ok ? 'success' : 'danger');
  }

  /**
   * Submit the review. It rests hidden until the other side submits too or the window closes
   * (P6-08), and the confirmation says so — someone who leaves a review and sees nothing appear
   * would reasonably think it failed.
   */
  async submitReview(): Promise<void> {
    const engagementId = this.job().engagementId;
    if (engagementId === null || this.rating() === 0 || this.busy()) {
      return;
    }
    this.busy.set(true);
    const ok = await this.customers.submitReview(engagementId, this.rating(), this.reviewBody().trim());
    await this.refresh();
    this.busy.set(false);
    await this.toast(ok ? 'review.submitted' : 'errors.generic', ok ? 'success' : 'danger');
  }

  private async refresh(): Promise<void> {
    const real = await this.customers.fetchJobDetail(this.id);
    if (real !== null) {
      this.job.set(real);
    }
  }

  private async toast(key: string, color: 'success' | 'danger'): Promise<void> {
    const toast = await this.toasts.create({
      message: this.translate.instant(key),
      duration: color === 'success' ? 3000 : 4000,
      position: 'top',
      color,
    });
    await toast.present();
  }

  openChat(): void {
    void this.router.navigate(['/workspace', this.id]);
  }

  /** An open job with no provider yet → the matched-providers shortlist (GET /jobs/{job}/providers). */
  findProviders(): void {
    void this.router.navigate(['/job', this.id, 'providers']);
  }

  openProvider(): void {
    const providerId = this.job().providerId;
    if (providerId) {
      void this.router.navigate(['/provider', providerId]);
    }
  }
}
