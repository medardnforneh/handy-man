import { CommonModule } from '@angular/common';
import { Component, OnDestroy, effect, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertController, IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { OfflineStripComponent } from '../../core/offline/offline-strip.component';
import { WriteQueue } from '../../core/offline/write-queue.service';
import { RealtimeService, Unsubscribe } from '../../core/realtime.service';
import { uuid } from '../../core/uuid';
import { Recording, VoiceRecorderService } from '../../core/voice-recorder.service';
import { CustomerService } from '../customer.service';
import { JobStatus, WorkspaceMessage, WorkspaceThread } from '../customer.models';
import { MoneyPipe } from '../money.pipe';

/** How long "typing…" stays up after the last whisper — no "stopped" event is ever sent. */
const TYPING_LINGER_MS = 4000;
/** At most one whisper this often, however fast the user types. */
const TYPING_WHISPER_EVERY_MS = 2000;

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
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe, OfflineStripComponent],
})
export class WorkspacePage implements OnDestroy {
  private readonly customers = inject(CustomerService);
  private readonly realtime = inject(RealtimeService);
  private readonly recorder = inject(VoiceRecorderService);
  private readonly queue = inject(WriteQueue);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly alerts = inject(AlertController);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  private readonly jobId = this.route.snapshot.paramMap.get('id') ?? '';

  /** Fixture thread first (instant, offline-safe); the real thread replaces it once loaded. */
  readonly thread = signal<WorkspaceThread | null>(this.customers.thread(this.jobId));
  readonly draft = signal('');
  readonly sending = signal(false);

  private unsubscribe: Unsubscribe = () => undefined;
  private unwatchReconnect: Unsubscribe = () => undefined;
  private unwatchTyping: Unsubscribe = () => undefined;
  private unwatchPresence: Unsubscribe = () => undefined;

  /** True while the other party has this thread open. Live only — never persisted. */
  readonly peerOnline = signal(false);

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

  /** Whether the write queue had anything owed on the previous change — see the effect below. */
  private hadPending = false;

  constructor() {
    void this.loadReal();
    document.addEventListener('visibilitychange', this.onVisible);

    // The moment the queue empties, everything it was holding is on the server — so re-read the
    // thread and let the real messages replace the optimistic ones. Watching the queue rather than
    // connectivity is deliberate: being back online is not the same as having caught up.
    effect(() => {
      const pending = this.queue.pending().length;
      if (pending === 0 && this.hadPending) {
        void this.reconcile();
      }
      this.hadPending = pending > 0;
    });
  }

  ngOnDestroy(): void {
    this.unsubscribe();
    this.unwatchReconnect();
    this.unwatchTyping();
    this.unwatchPresence();
    if (this.typingTimer !== null) {
      clearTimeout(this.typingTimer);
    }
    document.removeEventListener('visibilitychange', this.onVisible);

    // Stop any playback and release the blob URLs — an object URL lives until revoked, so leaving
    // them behind keeps every played voice note in memory for the life of the page.
    this.audio?.pause();
    this.recorder.cancel();
    for (const url of this.objectUrls.values()) {
      URL.revokeObjectURL(url);
    }
    this.objectUrls.clear();
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

    // Opening the thread IS reading it — clear the unread marker so the messages tab agrees with
    // what the user just saw. Best-effort and fire-and-forget; it must never delay the thread.
    if (real.conversationId !== null) {
      void this.customers.markChatRead(real.conversationId);
    }

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

    // Presence is its own channel (same rule, separate subscription) so it can never disturb the
    // message path. Tell the service who we are first, or we'd count ourselves as present.
    this.realtime.setSelfId(this.customers.me().id);
    this.unwatchPresence();
    this.unwatchPresence = this.realtime.onPresence(
      engagementId,
      (others) => this.peerOnline.set(others > 0),
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

  /**
   * Post the composed text (P5-02).
   *
   * The bubble appears immediately and the composer clears, because that is what sending a message
   * feels like — and on these networks waiting for a round trip before showing anything would mean
   * a chat that freezes for seconds at a time. If the write only got as far as the queue, the
   * bubble stays with a "waiting to send" mark rather than quietly pretending it arrived; when the
   * queue drains, `reconcile()` replaces it with the server's copy.
   */
  async send(): Promise<void> {
    const body = this.draft().trim();
    if (body === '' || this.sending()) {
      return;
    }
    this.sending.set(true);

    const localId = `local-${uuid()}`;
    this.append({
      id: localId,
      kind: 'text',
      mine: true,
      body,
      time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      delivery: 'queued',
    });
    this.draft.set('');

    const { outcome, thread } = await this.customers.sendMessage(this.jobId, body);
    if (outcome === 'sent' && thread !== null) {
      this.thread.set(thread); // the server's thread — the optimistic bubble goes with it
    } else if (outcome === 'failed') {
      this.mark(localId, 'failed');
    }
    this.sending.set(false);
  }

  /** Append one message to the open thread, if there is one. */
  private append(message: WorkspaceMessage): void {
    this.thread.update((current) =>
      current === null ? current : { ...current, messages: [...current.messages, message] });
  }

  /** Re-mark an optimistic bubble (queued → failed). */
  private mark(id: string, delivery: 'queued' | 'failed'): void {
    this.thread.update((current) => current === null ? current : {
      ...current,
      messages: current.messages.map((m) => (m.id === id ? { ...m, delivery } : m)),
    });
  }

  // --- Voice notes (P4-05) -----------------------------------------------------------------------

  /** Hidden rather than broken where the browser can't record at all. */
  readonly canRecord = VoiceRecorderService.supported;
  readonly isRecording = signal(false);
  readonly playingId = signal<string | null>(null);

  private audio: HTMLAudioElement | null = null;
  /** Blob URLs we created for playback — revoked on destroy, or they leak for the page's life. */
  private readonly objectUrls = new Map<string, string>();

  /**
   * Start recording, or stop and send. Kept as one control because that is how the affordance
   * reads: tap to speak, tap to send.
   */
  async toggleRecording(): Promise<void> {
    if (this.isRecording()) {
      this.isRecording.set(false);
      const take = await this.recorder.stop();
      if (take === null) {
        return; // nothing captured — silently drop rather than send an empty note
      }
      await this.sendVoice(take);
      return;
    }

    try {
      await this.recorder.start();
      this.isRecording.set(true);
    } catch {
      // Permission refused or no microphone. Not an error worth shouting about — the user simply
      // types instead.
      this.isRecording.set(false);
    }
  }

  private async sendVoice(take: Recording): Promise<void> {
    this.sending.set(true);
    try {
      await this.customers.sendVoiceNote(this.jobId, take);
      await this.reconcile();
    } finally {
      this.sending.set(false);
    }
  }

  /**
   * Play (or pause) a voice note. The media route is authorized, and an `<audio src>` cannot carry
   * the Bearer — so the bytes are fetched with the token and played from a blob URL, cached per
   * message so replaying doesn't refetch.
   */
  async playVoice(message: WorkspaceMessage): Promise<void> {
    if (this.playingId() === message.id) {
      this.audio?.pause();
      this.playingId.set(null);
      return;
    }

    const url = message.mediaUrl;
    if (!url) {
      return;
    }

    this.audio?.pause();
    try {
      let objectUrl = this.objectUrls.get(message.id);
      if (objectUrl === undefined) {
        objectUrl = await this.customers.voiceObjectUrl(url);
        this.objectUrls.set(message.id, objectUrl);
      }
      this.audio = new Audio(objectUrl);
      this.audio.onended = () => this.playingId.set(null);
      await this.audio.play();
      this.playingId.set(message.id);
    } catch {
      this.playingId.set(null);
    }
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

  // --- Reviewing a deliverable (P4-08) -----------------------------------------------------------
  //
  // The one narrated event in this thread the customer must ACT on. Remote work has no site visit
  // and no on-site report; it finishes when the customer accepts what was delivered, and until this
  // shipped nothing in the app could — the provider submitted into a chip that said so and stopped.
  //
  // Accepting stays here rather than moving to the job overview like the quote did, and for the
  // opposite reason: a quote is money and a commitment, worth a screen that shows every offer side
  // by side; a deliverable is a piece of work whose whole context — what was asked for, what was
  // sent, the conversation around it — is this thread.

  readonly reviewing = signal<string | null>(null);

  async acceptDeliverable(message: WorkspaceMessage): Promise<void> {
    const deliverable = message.deliverable;
    if (!deliverable || this.reviewing() !== null) {
      return;
    }

    this.reviewing.set(deliverable.id);
    const result = await this.customers.reviewDeliverable(deliverable.id, 'accept');
    this.reviewing.set(null);

    if (result.ok) {
      this.markReviewed(message.id, 'accepted');
    } else {
      await this.toast(result.detail ?? this.translate.instant('workspace.deliverable_failed'), 'danger');
    }
  }

  /**
   * A rejection carries the customer's own words, so it asks for them. The server requires a reason
   * and would refuse an empty one — but the real point is that the provider is about to redo work on
   * the strength of this sentence, and "rejected" alone is an instruction nobody can act on.
   */
  async rejectDeliverable(message: WorkspaceMessage): Promise<void> {
    const deliverable = message.deliverable;
    if (!deliverable || this.reviewing() !== null) {
      return;
    }

    const alert = await this.alerts.create({
      header: this.translate.instant('workspace.deliverable_reject_title'),
      message: this.translate.instant('workspace.deliverable_reject_body'),
      inputs: [{
        name: 'reason',
        type: 'textarea',
        placeholder: this.translate.instant('workspace.deliverable_reject_ph'),
        attributes: { maxlength: 2000 },
      }],
      buttons: [
        { text: this.translate.instant('common.cancel'), role: 'cancel' },
        { text: this.translate.instant('workspace.deliverable_reject_send'), role: 'confirm' },
      ],
    });
    await alert.present();

    const { role, data } = await alert.onDidDismiss<{ values?: { reason?: string } }>();
    const reason = (data?.values?.reason ?? '').trim();
    if (role !== 'confirm' || reason === '') {
      return;
    }

    this.reviewing.set(deliverable.id);
    const result = await this.customers.reviewDeliverable(deliverable.id, 'reject', reason);
    this.reviewing.set(null);

    if (result.ok) {
      this.markReviewed(message.id, 'rejected');
    } else {
      await this.toast(result.detail ?? this.translate.instant('workspace.deliverable_failed'), 'danger');
    }
  }

  // --- Claiming a warranty (P6-11) ---------------------------------------------------------------
  //
  // The warranty card is the customer's only copy of the warranty: there is no read endpoint for
  // one, so if this message is not in front of them, nothing in the product tells them they are
  // covered. Claiming from it spawns a real remedy job.

  readonly claiming = signal(false);

  async claimWarranty(message: WorkspaceMessage): Promise<void> {
    const warranty = message.warranty;
    if (!warranty || this.claiming()) {
      return;
    }

    const alert = await this.alerts.create({
      header: this.translate.instant('workspace.warranty_claim_title'),
      message: this.translate.instant('workspace.warranty_claim_body'),
      inputs: [{
        name: 'description',
        type: 'textarea',
        placeholder: this.translate.instant('workspace.warranty_claim_ph'),
        attributes: { maxlength: 5000 },
      }],
      buttons: [
        { text: this.translate.instant('common.cancel'), role: 'cancel' },
        { text: this.translate.instant('workspace.warranty_claim_send'), role: 'confirm' },
      ],
    });
    await alert.present();

    const { role, data } = await alert.onDidDismiss<{ values?: { description?: string } }>();
    const description = (data?.values?.description ?? '').trim();
    if (role !== 'confirm' || description === '') {
      return;
    }

    this.claiming.set(true);
    const result = await this.customers.claimWarranty(warranty.id, description);
    this.claiming.set(false);

    if (!result.ok) {
      // A 409 here is a real rule — the warranty has expired, or a claim is already open — and the
      // server says which. That sentence is worth more than "something went wrong".
      await this.toast(result.detail ?? this.translate.instant('workspace.warranty_claim_failed'), 'danger');
      return;
    }

    const thread = this.thread();
    if (thread !== null) {
      this.thread.set({
        ...thread,
        messages: thread.messages.map((m) =>
          m.id === message.id && m.warranty ? { ...m, warranty: { ...m.warranty, claimed: true } } : m,
        ),
      });
    }
    await this.toast(this.translate.instant('workspace.warranty_claimed'), 'success');
  }

  /**
   * Latch the card locally. The server 409s a second review, so the buttons have to go the moment
   * one lands — leaving them up would invite a refusal the customer did nothing to deserve.
   */
  private markReviewed(messageId: string, outcome: 'accepted' | 'rejected'): void {
    const thread = this.thread();
    if (thread === null) {
      return;
    }

    this.thread.set({
      ...thread,
      messages: thread.messages.map((m) =>
        m.id === messageId && m.deliverable
          ? { ...m, deliverable: { ...m.deliverable, reviewed: outcome } }
          : m,
      ),
    });
  }

  private async toast(message: string, color: 'success' | 'danger'): Promise<void> {
    const toast = await this.toasts.create({ message, duration: 3500, position: 'top', color });
    await toast.present();
  }

  /**
   * The job overview, where quotes are read and accepted.
   *
   * The quote card in this thread used to carry its own "Accept & pay" and "Counter" buttons, both
   * wired to nothing. Accepting spends money and commits to a provider — it belongs on the screen
   * that can show every quote received side by side and confirm the figures first.
   */
  openJob(): void {
    void this.router.navigate(['/job', this.jobId]);
  }
}
