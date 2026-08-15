import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AlertController, IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { OfflineStripComponent } from '../../core/offline/offline-strip.component';
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
  imports: [CommonModule, IonicModule, TranslatePipe, MoneyPipe, OfflineStripComponent],
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
    void this.customers.fetchJobDetail(this.id).then((real) => {
      if (real !== null) {
        this.job.set(real);
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
