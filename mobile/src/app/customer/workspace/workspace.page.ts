import { CommonModule } from '@angular/common';
import { Component, OnDestroy, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { RealtimeService, Unsubscribe } from '../../core/realtime.service';
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
export class WorkspacePage implements OnDestroy {
  private readonly customers = inject(CustomerService);
  private readonly realtime = inject(RealtimeService);
  private readonly route = inject(ActivatedRoute);

  private readonly jobId = this.route.snapshot.paramMap.get('id') ?? '';

  /** Fixture thread first (instant, offline-safe); the real thread replaces it once loaded. */
  readonly thread = signal<WorkspaceThread | null>(this.customers.thread(this.jobId));
  readonly draft = signal('');
  readonly sending = signal(false);

  private unsubscribe: Unsubscribe = () => undefined;

  constructor() {
    void this.loadReal();
  }

  ngOnDestroy(): void {
    this.unsubscribe();
  }

  /**
   * Fetch first, THEN subscribe: REST is authoritative and the socket only carries what happens
   * afterwards (P4-07). Doing it in this order means a message that lands mid-load is already in the
   * fetched thread rather than lost between the two.
   */
  private async loadReal(): Promise<void> {
    const real = await this.customers.fetchThread(this.jobId);
    if (real === null) {
      return;
    }
    this.thread.set(real);
    this.listen(real.engagementId);
  }

  private listen(engagementId: string | null): void {
    this.unsubscribe();
    if (engagementId === null) {
      return;
    }

    this.unsubscribe = this.realtime.onEngagementMessage(engagementId, (live) => {
      this.thread.update((current) => {
        if (current === null) {
          return current;
        }
        // The sender already appended it locally on send, and at-least-once delivery can repeat a
        // frame — so dedupe on id rather than trusting the socket to fire exactly once.
        if (current.messages.some((m) => m.id === live.id)) {
          return current;
        }
        return { ...current, messages: [...current.messages, this.customers.mapLiveMessage(live)] };
      });
    });
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
