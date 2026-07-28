import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { CustomerService } from '../customer.service';
import { JobStatus, WorkspaceThread } from '../customer.models';
import { MoneyPipe } from '../money.pipe';

/**
 * The engagement workspace — the chat IS the state machine (doc 06). Structured messages (quote,
 * milestone, arrival) are narrated by the SERVER and rendered as cards/chips inside the same thread;
 * the client only ever posts free-form text/voice (CLAUDE.md rule #11), which is why the composer has
 * no "submit quote" button. The thread loads from GET /jobs/{id}/messages, falling back to the demo
 * fixture when offline or when the job has no conversation yet.
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

  private readonly jobId = this.route.snapshot.paramMap.get('id') ?? '';

  /** Fixture thread first (instant, offline-safe); the real thread replaces it once loaded. */
  readonly thread = signal<WorkspaceThread | null>(this.customers.thread(this.jobId));
  readonly draft = signal('');
  readonly sending = signal(false);

  constructor() {
    void this.loadReal();
  }

  private async loadReal(): Promise<void> {
    const real = await this.customers.fetchThread(this.jobId);
    if (real !== null) {
      this.thread.set(real);
    }
  }

  onDraft(value: string | null | undefined): void {
    this.draft.set(value ?? '');
  }

  /** Post the composed text to the thread, then reflect the re-fetched thread. */
  async send(): Promise<void> {
    const body = this.draft().trim();
    if (body === '' || this.sending()) {
      return;
    }
    this.sending.set(true);
    const updated = await this.customers.sendMessage(this.jobId, body);
    if (updated !== null) {
      this.thread.set(updated);
      this.draft.set('');
    }
    this.sending.set(false);
  }

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
