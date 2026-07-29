import { CommonModule } from '@angular/common';
import { Component, OnDestroy, inject, signal } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { IonicModule } from '@ionic/angular';
import { TranslatePipe } from '@ngx-translate/core';
import { RealtimeService, Unsubscribe } from '../../core/realtime.service';
import { CustomerService } from '../customer.service';

/** How long "typing…" stays up after the last whisper — no "stopped" event is ever sent. */
const TYPING_LINGER_MS = 4000;
/** At most one whisper this often, however fast the user types. */
const TYPING_WHISPER_EVERY_MS = 2000;
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
  private unwatchReconnect: Unsubscribe = () => undefined;
  private unwatchTyping: Unsubscribe = () => undefined;

  /** True while another participant is typing — cleared by a timer, since "stopped" is never sent. */
  readonly peerTyping = signal(false);
  private typingTimer: ReturnType<typeof setTimeout> | null = null;
  private lastWhisperAt = 0;

  /** Refetch when the tab/app comes back to the foreground — see `reconcile()`. */
  private readonly onVisible = (): void => {
    if (document.visibilityState === 'visible') {
      void this.reconcile();
    }
  };

  constructor() {
    void this.loadReal();
    document.addEventListener('visibilitychange', this.onVisible);
  }

  ngOnDestroy(): void {
    this.unsubscribe();
    this.unwatchReconnect();
    this.unwatchTyping();
    if (this.typingTimer !== null) {
      clearTimeout(this.typingTimer);
    }
    document.removeEventListener('visibilitychange', this.onVisible);
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

    // Anything sent while the socket was away was never delivered, so a reconnect means the thread
    // is stale — REST is the record, the socket is only a notification (P4-07).
    this.unwatchReconnect();
    this.unwatchReconnect = this.realtime.onReconnect(() => void this.reconcile());
  }

  /**
   * Re-read the thread from REST and replace what's on screen. Used on reconnect and on returning
   * to the foreground — both are moments where live frames may have been missed. A failed refetch
   * leaves the current thread alone rather than blanking a conversation the user is reading.
   */
  private async reconcile(): Promise<void> {
    const fresh = await this.customers.fetchThread(this.jobId);
    if (fresh === null) {
      return;
    }
    this.thread.set(fresh);

    // ALWAYS rejoin, even on the same channel. When Reverb restarts it loses every subscription,
    // and pusher-js's automatic re-subscribe can fail and then never retry — leaving a connected
    // socket whose channel is silently `subscribed: false`, so no further message ever arrives
    // (observed). Tearing down and rejoining is what actually revives it. The rejoin can't loop:
    // the new channel's first `subscribed` fire is skipped by design.
    this.listen(fresh.engagementId);
  }

  private listen(engagementId: string | null): void {
    this.unsubscribe();
    if (engagementId === null) {
      return;
    }

    this.unsubscribe = this.realtime.onEngagementMessage(
      engagementId,
      (live) => {
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
      },
      // Live again on this channel — whatever we missed while away is only in REST.
      () => void this.reconcile(),
    );

    // Bound after the subscription above, because typing rides that same channel.
    this.unwatchTyping();
    this.unwatchTyping = this.realtime.onTyping(engagementId, (from) => {
      // A whisper never returns to its sender, but a second worker on the engagement could send
      // one — ignore our own id rather than showing "you are typing".
      if (from !== this.customers.me().id) {
        this.showPeerTyping();
      }
    });
  }

  /**
   * Show "typing…" and arm a timer to clear it. There is deliberately no "stopped typing" whisper:
   * the sender may close the app mid-word, and an indicator that can stick forever is worse than
   * one that expires on its own.
   */
  private showPeerTyping(): void {
    this.peerTyping.set(true);
    if (this.typingTimer !== null) {
      clearTimeout(this.typingTimer);
    }
    this.typingTimer = setTimeout(() => this.peerTyping.set(false), TYPING_LINGER_MS);
  }

  /** Throttled — Reverb rate-limits client events, and one whisper per keystroke would be abusive. */
  private notifyTyping(): void {
    const engagementId = this.thread()?.engagementId;
    if (!engagementId) {
      return;
    }
    const now = Date.now();
    if (now - this.lastWhisperAt < TYPING_WHISPER_EVERY_MS) {
      return;
    }
    this.lastWhisperAt = now;
    this.realtime.whisperTyping(engagementId, this.customers.me().id);
  }

  onDraft(value: string | null | undefined): void {
    this.draft.set(value ?? '');
    this.notifyTyping();
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
