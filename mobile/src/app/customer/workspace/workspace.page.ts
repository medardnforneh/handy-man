import { CommonModule } from '@angular/common';
import { Component, inject } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { CustomerService } from '../customer.service';
import { JobStatus, WorkspaceThread } from '../customer.models';
import { MoneyPipe } from '../money.pipe';

/**
 * The engagement workspace — the chat IS the state machine (doc 06). Structured messages (quote,
 * milestone, arrival) are narrated by the SERVER and rendered as cards inside the same thread; the
 * client only ever posts free-form text/voice (CLAUDE.md rule #11), which is why the composer has no
 * "submit quote" button.
 */
@Component({
  selector: 'app-workspace',
  templateUrl: './workspace.page.html',
  styleUrls: ['./workspace.page.scss'],
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe],
})
export class WorkspacePage {
  private readonly customers = inject(CustomerService);
  private readonly route = inject(ActivatedRoute);

  readonly thread: WorkspaceThread | null =
    this.customers.thread(this.route.snapshot.paramMap.get('id') ?? '');

  /** Map a job status onto a semantic pill tone — never a literal colour. */
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
        return 'tone-info';
    }
  }
}
