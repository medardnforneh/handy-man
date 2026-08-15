import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { IonicModule, ToastController } from '@ionic/angular';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { uuid } from '../../core/uuid';
import { MoneyPipe } from '../../customer/money.pipe';
import { Lead, QuoteDraft, QuoteLine, QuoteLineKind, SubmittedQuote } from '../provider.models';
import { ProviderService } from '../provider.service';

/** A quote 30 days out is the sensible default; the provider can shorten or extend it. */
const DEFAULT_VALIDITY_DAYS = 30;

/** yyyy-mm-dd for a date N days from now — the shape the date input and the API both take. */
function isoDaysFromNow(days: number): string {
  const d = new Date();
  d.setDate(d.getDate() + days);
  return d.toISOString().slice(0, 10);
}

/**
 * Lead detail + quote composer — how a provider answers an opportunity.
 *
 * A direct offer can be answered two ways: **accept** it as-is (P2-06, forms the engagement at the
 * offered price), or **quote** it (P2.5-01) when the work needs pricing. The quote is ITEMISED
 * because that is what the server stores and freezes — and the total shown here is a preview of the
 * server's own arithmetic over the lines, never an input, since a client-supplied total is not
 * trusted. Lines and terms freeze on submit; changing a live quote is a new version, not an edit.
 */
@Component({
  selector: 'app-provider-lead',
  templateUrl: './lead.page.html',
  styleUrls: ['./lead.page.scss'],
  imports: [CommonModule, FormsModule, IonicModule, TranslatePipe, MoneyPipe],
})
export class ProviderLeadPage {
  private readonly provider = inject(ProviderService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toasts = inject(ToastController);
  private readonly translate = inject(TranslateService);

  private readonly id = this.route.snapshot.paramMap.get('id') ?? '';
  readonly lead = signal<Lead | null>(this.provider.lead(this.id));

  /** A real incoming offer (from GET /provider/opportunities), so accept/quote hit the API. */
  readonly isReal = signal(false);
  readonly busy = signal(false);

  // --- Quote composer ---------------------------------------------------------------------------

  readonly quoteOpen = signal(false);
  readonly lines = signal<QuoteLine[]>([]);
  readonly deposit = signal(0);
  readonly notes = signal('');
  readonly validUntil = signal(isoDaysFromNow(DEFAULT_VALIDITY_DAYS));
  readonly touched = signal(false);

  readonly kinds: QuoteLineKind[] = ['labour', 'material', 'travel', 'other'];

  /** The same sum the server will compute from the lines — shown so the price is never a surprise. */
  readonly subtotalMinor = computed(() => this.lines().reduce(
    (total, l) => total + Math.round(l.quantity * l.unitPriceMinor),
    0,
  ));

  /** Every line needs a label, and the quote needs to be worth something. */
  readonly namedLines = computed(() => this.lines().filter((l) => l.label.trim() !== ''));
  readonly quoteValid = computed(() => this.namedLines().length > 0 && this.subtotalMinor() > 0);

  /** The deposit is captured into escrow on acceptance (P3-13) — it cannot exceed the quote. */
  readonly depositTooBig = computed(() => this.deposit() > this.subtotalMinor());

  readonly showErrors = computed(() => this.touched() && (!this.quoteValid() || this.depositTooBig()));

  constructor() {
    void this.loadReal();
  }

  private async loadReal(): Promise<void> {
    const real = await this.provider.fetchLead(this.id);
    if (real !== null) {
      this.lead.set(real);
      this.isReal.set(true);

      // Whether this job already carries a quote of ours decides what this screen offers: sending a
      // price, or changing the one that is already in front of the customer.
      if (real.jobId !== null) {
        this.ownQuote.set(await this.provider.fetchOwnQuote(real.jobId));
      }
    }
  }

  /** Accept the direct offer → forms the engagement, then hands off to the provider's work list. */
  async accept(): Promise<void> {
    if (this.busy()) {
      return;
    }
    this.busy.set(true);
    const result = await this.provider.acceptOffer(this.id);
    this.busy.set(false);

    if (result.ok) {
      await this.notify(this.translate.instant('pro.offer_accepted'), 'success');
      void this.router.navigate(['/pro/work']);
      return;
    }
    await this.notify(result.detail ?? this.translate.instant('pro.offer_accept_failed'), 'danger');
  }

  /**
   * The quote this provider already has in front of the customer, if any (P2.5-01).
   *
   * Its presence is what turns this screen from "price this job" into "change your price" — until
   * it was read, a provider who had quoted could only send a second, competing quote, and the
   * revise endpoint had no way in at all.
   */
  readonly ownQuote = signal<SubmittedQuote | null>(null);

  // --- Proposing a site visit (P2-07) ------------------------------------------------------------
  //
  // The third answer to a lead, and the honest one when the work cannot be priced from a
  // description: a leak behind a wall, a rewire in a house nobody has seen. Without it a provider
  // could only guess a number or walk away, which is how a marketplace fills up with quotes nobody
  // intends to honour.

  readonly visitOpen = signal(false);
  readonly visitWhen = signal(isoDaysFromNow(2));
  readonly visitChargeable = signal(false);
  readonly visitFee = signal(0);

  openVisit(): void {
    this.visitOpen.set(true);
  }

  closeVisit(): void {
    this.visitOpen.set(false);
  }

  async sendVisit(): Promise<void> {
    const jobId = this.lead()?.jobId;
    if (!jobId || this.busy()) {
      return;
    }

    const fee = this.visitChargeable() ? Math.max(0, Math.round(this.visitFee() || 0)) : 0;
    if (this.visitChargeable() && fee <= 0) {
      return;
    }

    // The server wants an instant, and the field gives a date. Nine in the morning is when this
    // work starts; proposing midnight because that is what a bare date parses to would be absurd.
    const scheduledFor = `${this.visitWhen()}T09:00:00`;

    this.busy.set(true);
    const result = await this.provider.scheduleSiteVisit(jobId, scheduledFor, fee > 0 ? fee : undefined);
    this.busy.set(false);

    if (result.ok) {
      this.visitOpen.set(false);
      await this.notify(this.translate.instant('pro.visit_sent'), 'success');
      return;
    }
    await this.notify(result.detail ?? this.translate.instant('pro.visit_failed'), 'danger');
  }

  openQuote(): void {
    this.touched.set(false);

    // Re-opening a live quote opens it on the terms that were actually sent, not on a blank form:
    // a revision is almost always a change to one line, and retyping the rest invites a new mistake
    // in a part the customer had already agreed with.
    const live = this.ownQuote();
    if (live !== null) {
      this.lines.set(live.lines.map((l) => ({ ...l })));
      this.deposit.set(live.depositMinor);
      this.notes.set(live.notes);
      this.validUntil.set(live.validUntil);
    }

    if (this.lines().length === 0) {
      this.addLine();
    }
    this.quoteOpen.set(true);
  }

  closeQuote(): void {
    this.quoteOpen.set(false);
  }

  addLine(): void {
    this.lines.update((rows) => [...rows, {
      id: uuid(),
      kind: 'labour',
      label: '',
      quantity: 1,
      unitPriceMinor: 0,
    }]);
  }

  removeLine(id: string): void {
    this.lines.update((rows) => rows.filter((l) => l.id !== id));
  }

  /** Write one field back, keeping the array immutable so the signal actually changes. */
  updateLine(id: string, patch: Partial<QuoteLine>): void {
    this.lines.update((rows) => rows.map((l) => (l.id === id ? { ...l, ...patch } : l)));
  }

  lineTotal(line: QuoteLine): number {
    return Math.round(line.quantity * line.unitPriceMinor);
  }

  async send(): Promise<void> {
    this.touched.set(true);
    if (!this.quoteValid() || this.depositTooBig() || this.busy()) {
      return;
    }

    const draft: QuoteDraft = {
      // A blank row is an abandoned line, not an error — drop them silently, as the report does.
      lines: this.namedLines(),
      depositMinor: Math.max(0, Math.round(this.deposit() || 0)),
      notes: this.notes(),
      validUntil: this.validUntil(),
    };

    // A live quote is REVISED, never re-sent. Submitting a second one would leave the customer
    // holding two live prices from the same provider with no way to tell which is meant.
    const live = this.ownQuote();

    this.busy.set(true);
    const result = live === null
      ? await this.provider.submitQuote(this.id, draft)
      : await this.provider.reviseQuote(live.id, draft);
    this.busy.set(false);

    if (result.ok) {
      this.quoteOpen.set(false);
      await this.notify(
        this.translate.instant(live === null ? 'pro.quote_sent' : 'pro.quote_revised'),
        'success',
      );
      void this.router.navigate(['/pro/opportunities']);
      return;
    }
    await this.notify(result.detail ?? this.translate.instant('pro.quote_failed'), 'danger');
  }

  private async notify(message: string, color: 'success' | 'danger'): Promise<void> {
    const toast = await this.toasts.create({ message, duration: 2800, position: 'top', color });
    await toast.present();
  }
}
