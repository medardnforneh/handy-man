import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ActionSheetController, AlertController, IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { ReportCategory, SafetyService } from '../../core/safety.service';
import { EmptyStateComponent } from '../../core/ui/empty-state.component';
import { CustomerService } from '../customer.service';

/**
 * The public provider profile — the discovery funnel's middle step: a customer taps a provider, sees
 * their reputation, then requests a quote. It mirrors `GET /v1/providers/{party}/reviews` + `/metrics`
 * and honours the two reputation disciplines from the backend:
 *   • the display rating is Bayesian-shrunk (P6-09) and null when unrated — never a bare prior;
 *   • the on-time rate is null below the sample-size floor (P6-12) — "100% (1 job)" is never shown.
 * Reviews are published double-blind results (P6-08); private notes never reach the client.
 */
@Component({
  selector: 'app-provider',
  templateUrl: './provider.page.html',
  styleUrls: ['./provider.page.scss'],
  imports: [CommonModule, FormsModule, IonicModule, TranslatePipe, EmptyStateComponent],
})
export class ProviderPage {
  private readonly customers = inject(CustomerService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly sheets = inject(ActionSheetController);
  private readonly alerts = inject(AlertController);
  private readonly safety = inject(SafetyService);
  private readonly i18n = inject(TranslateService);

  readonly sending = signal(false);

  private readonly partyId = this.route.snapshot.paramMap.get('id') ?? '';
  /** The job that led here — present when arriving from a job's provider search; enables the real fetch. */
  private readonly jobId = this.route.snapshot.queryParamMap.get('job');

  /** Fixture profile first (instant, offline-safe); the real public profile replaces it once loaded. */
  readonly provider = signal(this.customers.provider(this.partyId));

  constructor() {
    void this.loadReal();
  }

  /**
   * The real profile, keyed by party alone. This used to bail out entirely without a job in the
   * query string — so arriving from the discover rail showed demo data — because a headline could
   * only be read out of a job's match list. `GET /providers/{party}` removed that dependency.
   */
  private async loadReal(): Promise<void> {
    const real = await this.customers.fetchProviderProfile(this.partyId);
    if (real !== null) {
      this.provider.set(real);
    }
  }

  /** Below the P6-12 sample floor we have too little signal to display an on-time rate. */
  readonly hasEnoughSignal = computed(() => this.provider().onTimeRate !== null);

  /** A real job context turns the CTA into "send a direct offer" rather than the demo workspace open. */
  readonly hasJob = this.jobId !== null;

  /** Full + half + empty star glyphs for a rating out of 5 (display only). */
  stars(rating: number | null): ('full' | 'half' | 'empty')[] {
    const r = rating ?? 0;
    return Array.from({ length: 5 }, (_, i) => {
      if (r >= i + 1) return 'full';
      if (r >= i + 0.5) return 'half';
      return 'empty';
    });
  }

  onTimePercent(): number {
    return Math.round((this.provider().onTimeRate ?? 0) * 100);
  }

  /**
   * With a real job context this sends a direct offer to the provider (POST /jobs/{job}/offers) and
   * returns the customer to the job; without one (the demo entry from Discover) it opens the workspace
   * so the fixture conversation flow still works.
   */
  async requestQuote(): Promise<void> {
    if (this.jobId === null) {
      void this.router.navigate(['/workspace', this.provider().id]);
      return;
    }
    if (this.sending()) {
      return;
    }
    this.sending.set(true);
    const ok = await this.customers.sendOffer(this.jobId, this.partyId);
    this.sending.set(false);
    await this.toast(ok ? 'provider.offer_sent' : 'provider.offer_failed', ok ? 'success' : 'danger');
    if (ok) {
      void this.router.navigate(['/job', this.jobId]);
    }
  }

  private async toast(key: string, color: 'success' | 'danger'): Promise<void> {
    const t = await this.toasts.create({
      message: this.i18n.instant(key), duration: 2600, position: 'top', color,
    });
    await t.present();
  }

  // --- reporting and blocking (P6-07) -------------------------------------------------------------

  readonly categories: ReportCategory[] =
    ['no_show', 'fraud', 'harassment', 'safety', 'off_platform', 'spam', 'other'];

  readonly reportOpen = signal(false);
  /** Its own busy flag, not the page's: the offer button and this sheet are unrelated actions. */
  readonly reporting = signal(false);
  readonly category = signal<ReportCategory>('no_show');
  readonly reportBody = signal('');
  readonly reportTouched = signal(false);
  readonly reportBodyMissing = computed(() => this.reportTouched() && this.reportBody().trim() === '');

  /** The overflow. An action sheet rather than buttons on the page: findable without being urged. */
  async openActions(): Promise<void> {
    const sheet = await this.sheets.create({
      buttons: [
        { text: this.i18n.instant('report.title'), icon: 'flag-outline', handler: () => this.openReport() },
        { text: this.i18n.instant('block.action'), icon: 'ban-outline', role: 'destructive', handler: () => void this.block() },
        { text: this.i18n.instant('common.cancel'), role: 'cancel' },
      ],
    });
    await sheet.present();
  }

  openReport(): void {
    this.category.set('no_show');
    this.reportBody.set('');
    this.reportTouched.set(false);
    this.reportOpen.set(true);
  }

  closeReport(): void {
    this.reportOpen.set(false);
  }

  async sendReport(): Promise<void> {
    this.reportTouched.set(true);
    if (this.reportBodyMissing() || this.reporting()) {
      return;
    }
    this.reporting.set(true);
    const ok = await this.safety.report(this.partyId, this.category(), this.reportBody(), this.jobId ?? undefined);
    this.reporting.set(false);

    if (ok) {
      this.reportOpen.set(false);
    }
    await this.toast(ok ? 'report.sent' : 'report.failed', ok ? 'success' : 'danger');
  }

  /**
   * Block them. Confirmed first, and the confirmation says what it actually costs: the two are
   * never matched again in either direction. It is reversible from Safety, and the dialog says so
   * — a boundary nobody can find their way back out of is one people are afraid to use.
   */
  async block(): Promise<void> {
    const who = this.provider().name || this.provider().headline;
    const alert = await this.alerts.create({
      header: this.i18n.instant('block.confirm_title', { name: who }),
      message: this.i18n.instant('block.confirm_body'),
      buttons: [
        { text: this.i18n.instant('common.cancel'), role: 'cancel' },
        { text: this.i18n.instant('block.confirm_yes'), role: 'confirm' },
      ],
    });
    await alert.present();
    const { role } = await alert.onDidDismiss();
    if (role !== 'confirm') {
      return;
    }

    const ok = await this.safety.block(this.partyId);
    await this.toast(ok ? 'block.done' : 'block.failed', ok ? 'success' : 'danger');
    if (ok) {
      // Back to Discover: a profile you have just blocked is not a page to be left sitting on,
      // with a "request a quote" button that would now be refused.
      void this.router.navigate(['/tabs/discover']);
    }
  }
}
