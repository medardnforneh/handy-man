import { CommonModule } from '@angular/common';
import { Component, inject } from '@angular/core';
import { Router } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { JobStatus, JobSummary } from '../customer.models';
import { CustomerService } from '../customer.service';
import { MoneyPipe } from '../money.pipe';

/** The customer's jobs, with money and milestone progress at a glance. */
@Component({
  selector: 'app-jobs',
  templateUrl: './jobs.page.html',
  styleUrls: ['./jobs.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe],
})
export class JobsPage {
  private readonly customers = inject(CustomerService);
  private readonly router = inject(Router);

  readonly jobs: JobSummary[] = this.customers.listJobs();

  tone(status: JobStatus): string {
    switch (status) {
      case 'completed':
        return 'tone-success';
      case 'in_progress':
      case 'work_submitted':
        return 'tone-warning';
      case 'cancelled':
        return 'tone-danger';
      case 'open':
      case 'draft':
        return 'tone-neutral';
      default:
        return 'tone-info';
    }
  }

  open(job: JobSummary): void {
    void this.router.navigate(['/workspace', job.id]);
  }

  /** Milestone slots for the progress bar. */
  slots(job: JobSummary): number[] {
    return Array.from({ length: Math.max(job.milestonesTotal, 0) }, (_, i) => i);
  }
}
