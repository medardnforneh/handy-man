import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { type MobileRail, railFor } from '../../core/payment-methods';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertController, IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { OfflineStripComponent } from '../../core/offline/offline-strip.component';
import { DisputeCategory } from '../../api/api.service';
import { JobDetail, JobQuote, JobStatus, MilestoneStatus } from '../customer.models';
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
  imports: [CommonModule, FormsModule, IonicModule, TranslatePipe, MoneyPipe, OfflineStripComponent],
})
export class JobDetailPage {
  private readonly customers = inject(CustomerService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly alerts = inject(AlertController);
  private readonly translate = inject(TranslateService);
  /** Plain class, no DI: the confirmation needs the same grouped figures the card shows. */
  private readonly money = new MoneyPipe();

  private readonly id = this.route.snapshot.paramMap.get('id') ?? '';
  readonly job = signal<JobDetail>(this.customers.jobDetail(this.id));

  readonly busy = signal(false);
  readonly rating = signal(0);
  readonly reviewBody = signal('');
  readonly starValues = [1, 2, 3, 4, 5];

  /** The milestone waiting on this person — the footer's one filled button when there is one. */
  readonly awaitingApproval = computed(() => this.job().milestones.find((m) => m.status === 'submitted') ?? null);

  /** The split bar: released and still-held as shares of what was agreed (handoff: Job detail). */
  readonly releasedShare = computed(() => {
    const job = this.job();
    return job.agreedMinor > 0 ? Math.min(100, (job.releasedMinor / job.agreedMinor) * 100) : 0;
  });
  readonly heldShare = computed(() => {
    const job = this.job();
    return job.agreedMinor > 0 ? Math.min(100 - this.releasedShare(), (job.escrowHeldMinor / job.agreedMinor) * 100) : 0;
  });

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

  /**
   * Quotes received on this job. Null until read; the section is absent rather than empty in that
   * case, because "nobody has quoted yet" is a claim we should only make when we know it.
   */
  readonly quotes = signal<JobQuote[] | null>(null);

  /** What is actually acceptable — an engagement already exists once one has been accepted. */
  readonly liveQuotes = computed(() => (this.job().engagementId === null ? this.quotes() ?? [] : []));

  constructor() {
    // Show the fixture instantly, then swap in the real job (GET /jobs/{id}) if reachable.
    void this.customers.fetchJobDetail(this.id).then(async (real) => {
      if (real !== null) {
        this.job.set(real);

        // Only once there is an engagement: a dispute is attached to one, so before it exists there
        // is nothing to look up and no reason to spend a request finding that out.
        if (real.engagementId !== null) {
          this.dispute.set(await this.customers.fetchDispute(real.engagementId));
        }
      }
    });
    void this.loadQuotes();
  }

  private async loadQuotes(): Promise<void> {
    this.quotes.set(await this.customers.fetchQuotes(this.id));
  }

  /**
   * Accept a quote — the engagement forms, the milestone plan is generated and the deposit is
   * captured into escrow (P2.5-05).
   *
   * Confirmed first, with the two numbers that matter said out loud. This is the one action on this
   * screen that commits the customer to a price and a person, and it cannot be taken back from
   * here; a mis-tap on a card in a list is exactly how that happens.
   */
  async accept(quote: JobQuote): Promise<void> {
    if (this.busy() || quote.expired) {
      return;
    }

    const confirmed = await this.confirm(quote);
    if (!confirmed) {
      return;
    }

    this.busy.set(true);
    const result = await this.customers.acceptQuote(quote.id);
    await this.refresh();
    await this.loadQuotes();
    this.busy.set(false);

    if (result.ok) {
      await this.toast('quote.accepted', 'success');
      return;
    }
    // The server's own words: a refusal here means the quote lapsed, or another one already formed
    // the engagement — the single fact the customer needs, which "something went wrong" would hide.
    await this.toast(result.detail ?? this.translate.instant('quote.accept_failed'), 'danger', true);
  }

  private async confirm(quote: JobQuote): Promise<boolean> {
    const alert = await this.alerts.create({
      header: this.translate.instant('quote.confirm_title'),
      message: this.translate.instant('quote.confirm_body', {
        total: this.money.transform(quote.totalMinor),
        deposit: this.money.transform(quote.depositMinor),
        currency: this.translate.instant('money.currency'),
      }),
      buttons: [
        { text: this.translate.instant('common.cancel'), role: 'cancel' },
        { text: this.translate.instant('quote.confirm_yes'), role: 'confirm' },
      ],
    });
    await alert.present();
    const { role } = await alert.onDidDismiss();

    return role === 'confirm';
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

  private async toast(key: string, color: 'success' | 'danger', raw = false): Promise<void> {
    const toast = await this.toasts.create({
      // `raw` is for a message the SERVER wrote — it is already a sentence in the reader's
      // language, and running it through the translator would only fail to find a key.
      message: raw ? key : this.translate.instant(key),
      duration: color === 'success' ? 3000 : 4000,
      position: 'top',
      color,
    });
    await toast.present();
  }

  // --- Funding escrow (P3-04) --------------------------------------------------------------------
  //
  // Accepting a quote builds the milestone plan and collects nothing, so this screen could show
  // "In escrow" as a number the customer had no way to actually pay in. This is that way.
  //
  // The payment is PENDING when the call returns: the money moves when they answer the prompt on
  // their own handset, which happens outside this app. Nothing here may say "paid".

  readonly fundOpen = signal(false);
  readonly fundAmount = signal(0);
  readonly fundMsisdn = signal('');
  /** The rail the person chose, if they did; else what the number says (core/payment-methods). */
  readonly fundRailChoice = signal<MobileRail | null>(null);
  readonly fundRail = computed(() => this.fundRailChoice() ?? railFor(this.fundMsisdn()));
  readonly fundTouched = signal(false);

  /** What is agreed but neither held nor released yet — the honest default for the amount field. */
  readonly outstandingMinor = computed(() => {
    const job = this.job();
    return Math.max(0, job.agreedMinor - job.escrowHeldMinor - job.releasedMinor);
  });

  readonly canFund = computed(() => this.job().engagementId !== null && this.outstandingMinor() > 0);

  readonly fundInvalid = computed(
    () => this.fundTouched() && (this.fundAmount() <= 0 || this.fundMsisdn().trim() === ''),
  );

  openFund(): void {
    this.fundTouched.set(false);
    this.fundAmount.set(this.outstandingMinor());
    this.fundOpen.set(true);
  }

  closeFund(): void {
    this.fundOpen.set(false);
  }

  async fund(): Promise<void> {
    this.fundTouched.set(true);
    const engagementId = this.job().engagementId;
    const amount = Math.trunc(this.fundAmount());
    const msisdn = this.fundMsisdn().trim();
    if (engagementId === null || amount <= 0 || msisdn === '' || this.busy()) {
      return;
    }

    this.busy.set(true);
    const result = await this.customers.fundEscrow(engagementId, amount, msisdn, this.fundRail() ?? undefined);
    this.busy.set(false);

    if (!result.ok) {
      await this.toast(result.detail ?? this.translate.instant('fund.failed'), 'danger', result.detail !== undefined);
      return;
    }

    this.fundOpen.set(false);
    // "Check your phone", not "paid": the USSD prompt is where this actually completes, and the
    // escrow figure on this card must not move until the server says the money arrived.
    await this.toast('fund.pending', 'success');
  }

  /**
   * Book the same provider again (P8-05).
   *
   * Offered only on a finished job, next to the person who did it — which is both where the thought
   * occurs and the one place we know for certain the two have worked together, so the server's
   * "nothing to clone" refusal is not a trap laid for the customer.
   */
  async rebook(): Promise<void> {
    const providerId = this.job().providerId;
    if (providerId === null || this.busy()) {
      return;
    }

    this.busy.set(true);
    const result = await this.customers.rebook(providerId);
    this.busy.set(false);

    if (!result.ok) {
      await this.toast(result.detail ?? this.translate.instant('rebook.failed'), 'danger', result.detail !== undefined);
      return;
    }

    await this.toast('rebook.done', 'success');
    if (result.jobId !== undefined) {
      void this.router.navigate(['/job', result.jobId]);
    }
  }

  // --- When it goes wrong (P6-06 / P3-14) --------------------------------------------------------
  //
  // Two different actions, deliberately kept apart. A dispute asks a human to look at the case and
  // leaves the money where it is; a refund moves what is left in escrow back and ends the money side
  // of the engagement. Offering them as one control would let someone reach for "I have a problem"
  // and unwind the payment by accident.

  readonly disputeOpen = signal(false);
  readonly disputeCategory = signal<DisputeCategory>('quality');
  readonly disputeBody = signal('');
  readonly disputeTouched = signal(false);
  readonly dispute = signal<{ category: string; status: string; resolutionNote: string | null } | null>(null);

  readonly disputeCategories: DisputeCategory[] = ['quality', 'payment', 'no_show', 'scope', 'safety', 'other'];

  readonly disputeBodyMissing = computed(() => this.disputeTouched() && this.disputeBody().trim() === '');

  /** Both only make sense once money and a commitment exist — that is, once there is an engagement. */
  readonly canDispute = computed(() => this.job().engagementId !== null);

  /** Nothing left in escrow is nothing to refund; the button would only ever produce a refusal. */
  readonly canRefund = computed(() => this.job().engagementId !== null && this.job().escrowHeldMinor > 0);

  openDispute(): void {
    this.disputeTouched.set(false);
    this.disputeOpen.set(true);
  }

  closeDispute(): void {
    this.disputeOpen.set(false);
  }

  async sendDispute(): Promise<void> {
    this.disputeTouched.set(true);
    const body = this.disputeBody().trim();
    const engagementId = this.job().engagementId;
    if (body === '' || engagementId === null || this.busy()) {
      return;
    }

    this.busy.set(true);
    const result = await this.customers.raiseDispute(engagementId, this.disputeCategory(), body);
    this.busy.set(false);

    if (!result.ok) {
      await this.toast(result.detail ?? this.translate.instant('dispute.failed'), 'danger', result.detail !== undefined);
      return;
    }

    this.disputeOpen.set(false);
    this.disputeBody.set('');
    this.disputeTouched.set(false);
    this.dispute.set(await this.customers.fetchDispute(engagementId));
    await this.toast('dispute.raised', 'success');
  }

  /**
   * Refunding is confirmed first, and the confirmation names the amount. This is the customer's own
   * money coming back, but it is also the end of the engagement's money — a provider mid-job stops
   * being paid for it — so it should never happen on a mis-tap.
   */
  async refund(): Promise<void> {
    const engagementId = this.job().engagementId;
    if (engagementId === null || this.busy()) {
      return;
    }

    const held = this.money.transform(this.job().escrowHeldMinor);
    const alert = await this.alerts.create({
      header: this.translate.instant('refund.confirm_title'),
      message: this.translate.instant('refund.confirm_body', {
        amount: held,
        currency: this.translate.instant('money.currency'),
      }),
      buttons: [
        { text: this.translate.instant('common.cancel'), role: 'cancel' },
        { text: this.translate.instant('refund.confirm_action'), role: 'confirm' },
      ],
    });
    await alert.present();

    const { role } = await alert.onDidDismiss();
    if (role !== 'confirm') {
      return;
    }

    this.busy.set(true);
    const result = await this.customers.refundEscrow(engagementId, 'customer_requested');
    this.busy.set(false);

    if (!result.ok) {
      await this.toast(result.detail ?? this.translate.instant('refund.failed'), 'danger', result.detail !== undefined);
      return;
    }

    // Re-read rather than assume: the refund's effect on the held and released figures is the
    // server's arithmetic, and this card's whole job is to state those two numbers accurately.
    const fresh = await this.customers.fetchJobDetail(this.id);
    if (fresh !== null) {
      this.job.set(fresh);
    }
    await this.toast('refund.done', 'success');
  }

  // --- Share this visit (P6-05) ------------------------------------------------------------------
  //
  // A stranger is coming to someone's home. The person most likely to want to know about it — a
  // sister, a neighbour, whoever is asked to "check on me at four" — is not in this app and should
  // not have to be, so what they get is a plain web page: the provider's first name, the status,
  // the quarter, and when the link dies. Never the street address (P6-05).
  //
  // The raw token is returned exactly once and cannot be read back, so it is held here for as long
  // as the screen lives. A link that is lost is re-minted, never recovered — which is also why the
  // URL is shown in full rather than hidden behind a "Copy" that could silently fail.

  readonly shareUrl = signal<string | null>(null);
  readonly shareId = signal<string | null>(null);
  readonly shareExpires = signal<string | null>(null);
  readonly sharing = signal(false);

  /** Only a live engagement can be shared: before one exists there is no visit to tell anyone about. */
  readonly canShare = computed(() => this.job().engagementId !== null);

  async createShare(): Promise<void> {
    const engagementId = this.job().engagementId;
    if (engagementId === null || this.sharing()) {
      return;
    }

    this.sharing.set(true);
    const result = await this.customers.createShare(engagementId);
    this.sharing.set(false);

    if (!result.ok) {
      await this.toast(result.detail ?? this.translate.instant('share.create_failed'), 'danger', result.detail !== undefined);
      return;
    }

    this.shareId.set(result.id ?? null);
    this.shareUrl.set(result.url ?? null);
    this.shareExpires.set(result.expiresAt ?? null);
  }

  /**
   * Copy, rather than send. The app never sends this on someone's behalf — where it goes and to whom
   * is exactly the decision the person is making, and it is theirs.
   */
  async copyShare(): Promise<void> {
    const url = this.shareUrl();
    if (url === null) {
      return;
    }

    try {
      await navigator.clipboard.writeText(url);
      await this.toast('share.copied', 'success');
    } catch {
      // The link is on screen in full, so a clipboard the browser refuses is an inconvenience
      // rather than a dead end. Say which one happened instead of claiming success.
      await this.toast('share.copy_failed', 'danger');
    }
  }

  async revokeShare(): Promise<void> {
    const id = this.shareId();
    if (id === null || this.sharing()) {
      return;
    }

    this.sharing.set(true);
    const ok = await this.customers.revokeShare(id);
    this.sharing.set(false);

    if (!ok) {
      await this.toast('share.revoke_failed', 'danger');
      return;
    }

    this.shareId.set(null);
    this.shareUrl.set(null);
    this.shareExpires.set(null);
    await this.toast('share.revoked', 'success');
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
