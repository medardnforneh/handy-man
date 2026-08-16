import { CommonModule } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { OfflineStripComponent } from '../../core/offline/offline-strip.component';
import { EmptyStateComponent } from '../../core/ui/empty-state.component';
import { JobStatus } from '../../customer/customer.models';
import { ActiveWork, ProviderSiteVisit } from '../provider.models';
import { MoneyPipe } from '../../customer/money.pipe';
import { ProviderService } from '../provider.service';

/** Work tab — the jobs a provider is actively delivering; each opens its workspace thread. */
@Component({
  selector: 'app-provider-work',
  templateUrl: './work.page.html',
  styleUrls: ['./work.page.scss'],
  imports: [CommonModule, FormsModule, IonicModule, TranslatePipe, MoneyPipe, OfflineStripComponent, EmptyStateComponent],
})
export class ProviderWorkPage {
  private readonly provider = inject(ProviderService);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  /** Fixture list first (instant, offline-safe); the real engagements replace it once loaded. */
  readonly active = signal<ActiveWork[]>(this.provider.listActive());

  /**
   * Visits this provider has booked but not yet closed (P2.5-04).
   *
   * They live on the work tab rather than on a screen of their own because they are the same kind
   * of thing as the list beneath them: somewhere the provider has said they will be. A visit is not
   * an engagement — nobody has agreed a price yet — which is why it is its own section and not a row
   * in the work list.
   */
  readonly visits = signal<ProviderSiteVisit[]>([]);

  /** The visit being closed, so its sheet knows which one it is. */
  readonly closing = signal<ProviderSiteVisit | null>(null);
  readonly outcome = signal('');
  readonly busy = signal(false);

  constructor() {
    void this.load();
  }

  private async load(): Promise<void> {
    const real = await this.provider.fetchActive();
    if (real !== null) {
      this.active.set(real);
    }

    const visits = await this.provider.fetchSiteVisits();
    if (visits !== null) {
      // Only the open ones. A completed visit is history, and this list is a to-do.
      this.visits.set(visits.filter((v) => v.status === 'scheduled'));
    }
  }

  openClose(visit: ProviderSiteVisit): void {
    this.outcome.set('');
    this.closing.set(visit);
  }

  cancelClose(): void {
    this.closing.set(null);
  }

  /**
   * Close the visit with what was found there.
   *
   * The notes are optional to the server and asked for anyway: the provider went to look at
   * something, and what they saw is what the quote that follows gets argued from. A visit closed
   * with nothing written down is a trip nobody can account for later.
   */
  async completeVisit(): Promise<void> {
    const visit = this.closing();
    if (visit === null || this.busy()) {
      return;
    }

    const notes = this.outcome().trim();

    this.busy.set(true);
    const result = await this.provider.completeSiteVisit(visit.id, notes === '' ? undefined : notes);
    this.busy.set(false);

    if (!result.ok) {
      await this.notify(result.detail ?? this.translate.instant('pro.visit_close_failed'), 'danger');
      return;
    }

    this.closing.set(null);
    this.visits.update((rows) => rows.filter((v) => v.id !== visit.id));
    await this.notify(this.translate.instant('pro.visit_closed'), 'success');
  }

  // No "quote from here" shortcut. The composer lives on the lead, and the only way to send someone
  // there is a route the opportunities list does not filter on — so the button would land them in an
  // unfiltered feed to hunt for the job they just visited. That is a dead affordance dressed as a
  // convenience, and this codebase has removed several already.

  private async notify(message: string, color: 'success' | 'danger'): Promise<void> {
    const toast = await this.toasts.create({ message, duration: 3000, position: 'top', color });
    await toast.present();
  }

  tone(status: JobStatus): string {
    switch (status) {
      case 'completed': return 'tone-success';
      case 'in_progress': case 'work_submitted': return 'tone-warning';
      case 'cancelled': return 'tone-danger';
      default: return 'tone-info';
    }
  }

  open(work: ActiveWork): void {
    void this.router.navigate(['/work', work.id]);
  }

  /** With nothing in flight, the useful next screen is the one with work to bid on. */
  browseOpportunities(): void {
    void this.router.navigate(['/pro/opportunities']);
  }
}
