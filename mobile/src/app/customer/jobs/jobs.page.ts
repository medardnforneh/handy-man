import { CommonModule } from '@angular/common';
import { Component, computed, inject } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { OfflineStripComponent } from '../../core/offline/offline-strip.component';
import { EmptyStateComponent } from '../../core/ui/empty-state.component';
import { FollowUpsComponent } from '../../core/ui/follow-ups.component';
import { JobStatus, JobSummary } from '../customer.models';
import { CustomerService } from '../customer.service';
import { MoneyPipe } from '../money.pipe';

/** Jobs that are neither finished nor abandoned. */
const ACTIVE: ReadonlySet<JobStatus> = new Set(['engaged', 'scheduled', 'in_progress', 'work_submitted']);
/** Posted and waiting for a provider. */
const OPEN: ReadonlySet<JobStatus> = new Set(['draft', 'open', 'offered']);

/**
 * Home: the money held, then the jobs that need this person, then the rest (handoff structural
 * change 1). The old screen was five equal cards, and nothing distinguished the job needing
 * approval from the four that did not.
 */
@Component({
  selector: 'app-jobs',
  templateUrl: './jobs.page.html',
  styleUrls: ['./jobs.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe, OfflineStripComponent, EmptyStateComponent, FollowUpsComponent],
})
export class JobsPage {
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);

  readonly jobs = this.customers.jobs;
  /** "Still asking" vs "you have none" — an empty list alone cannot tell them apart. */
  readonly loaded = this.customers.jobsLoaded;
  readonly me = this.customers.me;

  /** The escrow figure: everything still held across active engagements. */
  readonly held = computed(() => this.jobs().reduce((sum, j) => sum + (ACTIVE.has(j.status) ? j.escrowHeldMinor : 0), 0));
  readonly released = computed(() => this.jobs().reduce((sum, j) => sum + j.releasedMinor, 0));
  readonly active = computed(() => this.jobs().filter((j) => ACTIVE.has(j.status)));
  /** A submitted milestone waiting on approval — the one derived value the design adds. */
  readonly needYou = computed(() => this.active().filter((j) => j.needsApproval));
  readonly running = computed(() => this.active().filter((j) => !j.needsApproval));
  readonly openJobs = computed(() => this.jobs().filter((j) => OPEN.has(j.status)));
  readonly done = computed(() => this.jobs().filter((j) => !ACTIVE.has(j.status) && !OPEN.has(j.status)));

  tone(status: JobStatus): string {
    switch (status) {
      case 'completed':
        return 'tone-success';
      case 'in_progress':
      case 'work_submitted':
        return 'tone-warning';
      case 'cancelled':
        return 'tone-danger';
      default:
        return 'tone-neutral';
    }
  }

  /** The provider's monogram, or the job's own initial when nobody has taken it yet. */
  initials(job: JobSummary): string {
    const source = job.providerName || job.title;
    const parts = source.trim().split(/\s+/).filter(Boolean);
    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase();
  }

  open(job: JobSummary): void {
    void this.router.navigate(['/job', job.id]);
  }

  postRequest(): void {
    void this.router.navigate(['/new-job']);
  }

  /** The bell: the nudges list lives at the top of this screen, so the bell brings it into view. */
  followUps(): void {
    document.querySelector('app-follow-ups')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  account(): void {
    void this.router.navigate(['/tabs', 'account']);
  }
}
