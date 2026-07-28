import { Injectable, inject } from '@angular/core';
import { ApiService } from '../api/api.service';
import { Accent } from '../customer/customer.models';
import { LocaleService } from '../core/locale.service';
import {
  ActiveWork, Lead, Payout, PayoutStatus, ProviderStats, ReportDraft, WorkDetail, WorkStatus,
  ProviderWallet,
} from './provider.models';

/** The outcome of one execution mutation: accepted, or refused with the server's own explanation. */
export interface MutationResult {
  ok: boolean;
  detail?: string;
}

/** Up to two initials from a display name, for the customer avatar. */
function initialsOf(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) {
    return '?';
  }
  return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
}

/**
 * A best-effort GPS fix for check-in/out. Deliberately forgiving: a short timeout and a null result
 * on refusal or failure, because the server accepts a session without coordinates — a worker with a
 * dead GPS must still be able to check in.
 */
function currentPosition(): Promise<{ latitude: number; longitude: number; accuracyM?: number } | null> {
  if (typeof navigator === 'undefined' || !navigator.geolocation) {
    return Promise.resolve(null);
  }
  return new Promise((resolve) => {
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({
        latitude: pos.coords.latitude,
        longitude: pos.coords.longitude,
        accuracyM: pos.coords.accuracy ?? undefined,
      }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 },
    );
  });
}

/** One entry of the provider opportunity feed (an offer with its PII-minimised job), as the API sends it. */
type ApiOpportunity = Awaited<ReturnType<ApiService['opportunities']>>[number];

/** Map the API payment status onto the provider's simple payout badge (paid / pending / failed). */
function mapPayoutStatus(status: string): PayoutStatus {
  switch (status) {
    case 'succeeded':
      return 'paid';
    case 'failed':
    case 'expired':
      return 'failed';
    default:
      return 'pending'; // pending / processing
  }
}

/** A deterministic accent from an id, so a lead keeps the same colour across renders. */
const LEAD_ACCENTS: Accent[] = ['brand', 'info', 'warning', 'muted'];
function accentFor(id: string): Accent {
  let sum = 0;
  for (const ch of id) {
    sum = (sum + ch.charCodeAt(0)) % LEAD_ACCENTS.length;
  }
  return LEAD_ACCENTS[sum];
}

/**
 * Provider-section data. Earnings, opportunities, the work list and the work detail — including its
 * check-in / status / report mutations — now run against the real API; the remaining surfaces keep
 * their fixtures. Every method falls back to its fixture when there's no session or the device is
 * offline, so the whole section stays demoable unconnected, and the shapes match the API so wiring
 * the rest is a per-method change.
 */
@Injectable({ providedIn: 'root' })
export class ProviderService {
  private readonly api = inject(ApiService);
  private readonly locales = inject(LocaleService);

  /** Real opportunities cached by offer id, so the lead detail can resolve one after the feed loads. */
  private readonly realLeads = new Map<string, Lead>();

  /** Engagement ids the API has confirmed — the switch that routes mutations to the server. */
  private readonly realWork = new Set<string>();

  readonly name = 'Atelier Nkeng';
  readonly initials = 'AN';
  readonly verified = true;
  readonly rating = 4.9;
  readonly verificationTier = 2; // 0 none · 1 phone · 2 ID · 3 ID + license (P6-03)
  readonly skills = ['Plomberie', 'Fuites', 'Chauffe-eau', 'Sanitaires'];
  readonly serviceArea = 'Douala — rayon de 15 km';

  /** Whether the provider is currently accepting new jobs (a soft availability switch). */
  private available = true;

  isAvailable(): boolean {
    return this.available;
  }

  setAvailable(value: boolean): void {
    this.available = value;
  }

  private readonly wallet: ProviderWallet = {
    availableMinor: 640000, pendingPayoutMinor: 150000, currency: 'XAF',
  };

  private readonly stats: ProviderStats = {
    activeJobs: 3, rating: 4.9, completed90d: 34, onTimeRate: 0.94,
  };

  private readonly leads: Lead[] = [
    {
      id: 'l1', reference: 'JOB-8P4K2', title: 'Chauffe-eau ne chauffe plus', skill: 'Plomberie',
      mode: 'onsite', area: 'Bonapriso, Douala', budgetMinor: 450000, postedAgo: 'Il y a 12 min',
      accent: 'brand', details: 'Le chauffe-eau électrique de 100 L ne chauffe plus depuis hier. Accès facile, immeuble avec ascenseur.',
    },
    {
      id: 'l2', reference: 'JOB-3M9Q7', title: 'Fuite robinet cuisine', skill: 'Plomberie',
      mode: 'onsite', area: 'Akwa, Douala', budgetMinor: 120000, postedAgo: 'Il y a 1 h',
      accent: 'info', details: 'Robinet mitigeur qui goutte en continu. Pièce probablement à remplacer.',
    },
    {
      id: 'l3', reference: 'JOB-6T1B5', title: 'Débouchage canalisation', skill: 'Plomberie',
      mode: 'onsite', area: 'Bonanjo, Douala', budgetMinor: null, postedAgo: 'Il y a 3 h',
      accent: 'warning', details: 'Évier de cuisine bouché. Devis souhaité avant intervention.',
    },
  ];

  private readonly active: ActiveWork[] = [
    { id: 'j1', reference: 'JOB-7K2M9', title: 'Fuite sous l’évier', customerName: 'Jean M.', status: 'in_progress', mode: 'onsite', accent: 'brand' },
    { id: 'a2', reference: 'JOB-5RN8K', title: 'Tableau électrique', customerName: 'Sandrine B.', status: 'engaged', mode: 'onsite', accent: 'info' },
    { id: 'a3', reference: 'JOB-2HW6P', title: 'Étagères sur mesure', customerName: 'Paul T.', status: 'work_submitted', mode: 'onsite', accent: 'warning' },
  ];

  private readonly payouts: Payout[] = [
    { id: 'po1', reference: 'PO-4KX92', amountMinor: 150000, status: 'pending', date: 'Aujourd’hui' },
    { id: 'po2', reference: 'PO-9TA31', amountMinor: 425000, status: 'paid', date: '18 juil.' },
    { id: 'po3', reference: 'PO-2MB77', amountMinor: 300000, status: 'paid', date: '9 juil.' },
    { id: 'po4', reference: 'PO-6RC08', amountMinor: 180000, status: 'failed', date: '2 juil.' },
  ];

  getWallet(): ProviderWallet {
    return this.wallet;
  }

  listPayouts(): Payout[] {
    return this.payouts;
  }

  /**
   * The real earnings summary (GET /provider/earnings) — the withdrawable payable balance (net of
   * reserved payouts), the reserved amount, and the payout history. Returns null when there's no
   * session / offline, so the caller keeps the demo wallet + payouts.
   */
  async fetchEarnings(): Promise<{ wallet: ProviderWallet; payouts: Payout[] } | null> {
    try {
      const e = await this.api.earnings();
      const wallet: ProviderWallet = {
        availableMinor: e.payable_available.amount_minor,
        pendingPayoutMinor: e.payable_pending.amount_minor,
        currency: e.payable_available.currency,
      };
      const payouts: Payout[] = e.payouts.map((p) => ({
        id: p.id,
        reference: (p.external_ref ?? p.id).slice(0, 12).toUpperCase(),
        amountMinor: p.amount.amount_minor,
        status: mapPayoutStatus(p.status),
        date: new Date(p.requested_at).toLocaleDateString(),
      }));
      return { wallet, payouts };
    } catch {
      return null;
    }
  }

  getStats(): ProviderStats {
    return this.stats;
  }

  listLeads(): Lead[] {
    return this.leads;
  }

  lead(id: string): Lead | null {
    return this.realLeads.get(id) ?? this.leads.find((l) => l.id === id) ?? null;
  }

  /**
   * The real opportunity feed (GET /provider/opportunities) — the provider's live incoming direct
   * offers, each mapped from the offer + its PII-minimised job. Caches them by offer id so the lead
   * detail resolves. Returns null when there's no session / offline, so the caller keeps the fixtures.
   */
  async fetchOpportunities(): Promise<Lead[] | null> {
    try {
      const offers = await this.api.opportunities();
      this.realLeads.clear();
      const leads = offers.map((o) => this.mapOffer(o));
      for (const lead of leads) {
        this.realLeads.set(lead.id, lead);
      }
      return leads;
    } catch {
      return null;
    }
  }

  /** Ensure the feed is loaded, then resolve one lead by offer id (deep-link safe). */
  async fetchLead(id: string): Promise<Lead | null> {
    if (!this.realLeads.has(id)) {
      await this.fetchOpportunities();
    }
    return this.realLeads.get(id) ?? null;
  }

  /**
   * Accept a direct offer → forms the engagement (POST /offers/{offer}/accept). On success the offer
   * leaves the feed. A fact gate (e.g. missing ID verification for an on-site job) comes back as a
   * problem+json whose `detail` we surface; any other failure returns a generic flag.
   */
  async acceptOffer(offerId: string): Promise<MutationResult> {
    const result = await this.attempt(() => this.api.acceptOffer(offerId));
    if (result.ok) {
      this.realLeads.delete(offerId);
    }
    return result;
  }

  /** Map one API offer (+ embedded coarse job) onto the provider's lead card/detail model. */
  private mapOffer(o: ApiOpportunity): Lead {
    const job = o.job ?? null;
    const loc = job?.location ?? null;
    const area = loc ? [loc.quarter, loc.city].filter(Boolean).join(', ') : '';
    return {
      id: o.id,
      reference: job?.reference ?? '',
      title: job?.title ?? '',
      skill: '', // the coarse job carries no skill label; the card leads with the title
      mode: (job?.engagement_mode ?? 'onsite') as Lead['mode'],
      area,
      budgetMinor: o.amount?.amount_minor ?? job?.budget?.amount_minor ?? null,
      postedAgo: o.created_at ? this.relativeTime(o.created_at) : '',
      accent: accentFor(o.id),
      details: job?.description ?? o.message ?? '',
    };
  }

  /** A localised "x minutes ago" from an ISO timestamp, in the provider's current UI locale. */
  private relativeTime(iso: string): string {
    const diffMs = Date.now() - new Date(iso).getTime();
    const rtf = new Intl.RelativeTimeFormat(this.locales.current, { numeric: 'auto' });
    const mins = Math.round(diffMs / 60000);
    if (Math.abs(mins) < 60) {
      return rtf.format(-mins, 'minute');
    }
    const hours = Math.round(mins / 60);
    if (Math.abs(hours) < 24) {
      return rtf.format(-hours, 'hour');
    }
    return rtf.format(-Math.round(hours / 24), 'day');
  }

  private readonly workDetails: Record<string, WorkDetail> = {
    j1: {
      id: 'j1', reference: 'JOB-7K2M9', title: 'Fuite sous l’évier', customerName: 'Jean M.',
      customerInitials: 'JM', mode: 'onsite', addressLine: 'Rue 1.234, Akwa, Douala', accent: 'brand',
      supportsCheckIn: true, supportsReport: true, usesDeliverables: false,
      checkedIn: false, status: 'engaged', reportSubmitted: false, deliverables: [],
    },
    a2: {
      id: 'a2', reference: 'JOB-5RN8K', title: 'Tableau électrique', customerName: 'Sandrine B.',
      customerInitials: 'SB', mode: 'onsite', addressLine: 'Bonapriso, Douala', accent: 'info',
      supportsCheckIn: true, supportsReport: true, usesDeliverables: false,
      checkedIn: false, status: 'engaged', reportSubmitted: false, deliverables: [],
    },
    a3: {
      id: 'a3', reference: 'JOB-2HW6P', title: 'Étagères sur mesure', customerName: 'Paul T.',
      customerInitials: 'PT', mode: 'remote', addressLine: null, accent: 'warning',
      supportsCheckIn: false, supportsReport: false, usesDeliverables: true,
      checkedIn: false, status: 'started', reportSubmitted: false,
      deliverables: [
        { id: 'd1', title: 'Plans d’atelier v2', status: 'accepted', submittedAt: null, rejectReason: null },
      ],
    },
  };

  listActive(): ActiveWork[] {
    return this.active;
  }

  /**
   * The real active-work list (GET /provider/work) — the provider's in-flight engagements. The row id
   * is the ENGAGEMENT id, which the work-detail check-in/status/report actions target. Returns null
   * when there's no session / offline, so the caller keeps the fixture list.
   */
  async fetchActive(): Promise<ActiveWork[] | null> {
    try {
      const rows = await this.api.work();
      return rows.map((w) => ({
        id: w.id,
        reference: w.reference,
        title: w.title,
        customerName: w.customer_name ?? '',
        status: w.job_status as ActiveWork['status'],
        mode: w.engagement_mode,
        accent: accentFor(w.id),
      }));
    } catch {
      return null;
    }
  }

  /** The provider's execution view of a job. Falls back to a minimal detail from the active list. */
  workDetail(id: string): WorkDetail {
    const found = this.workDetails[id];
    if (found) {
      return found;
    }
    const work = this.active.find((w) => w.id === id);
    return {
      id, reference: work?.reference ?? 'JOB-—', title: work?.title ?? '',
      customerName: work?.customerName ?? '', customerInitials: (work?.customerName ?? '?').charAt(0),
      mode: work?.mode ?? 'onsite', addressLine: null, accent: work?.accent ?? 'muted',
      supportsCheckIn: (work?.mode ?? 'onsite') !== 'remote',
      supportsReport: (work?.mode ?? 'onsite') !== 'remote',
      usesDeliverables: (work?.mode ?? 'onsite') !== 'onsite',
      checkedIn: false, status: 'engaged', reportSubmitted: false, deliverables: [],
    };
  }

  /**
   * The real execution view of one engagement (GET /provider/work/{engagement}) — the exact site
   * address plus this worker's derived state. Remembering that the id is real is what routes the
   * mutations to the API rather than the fixture. Returns null when there's no session / offline.
   */
  async fetchWorkDetail(id: string): Promise<WorkDetail | null> {
    try {
      const d = await this.api.workDetail(id);
      const addr = d.address ?? null;
      const detail: WorkDetail = {
        id: d.id,
        reference: d.reference,
        title: d.title,
        customerName: d.customer_name ?? '',
        customerInitials: initialsOf(d.customer_name ?? ''),
        mode: d.engagement_mode,
        addressLine: addr ? [addr.line1, addr.quarter, addr.city].filter(Boolean).join(', ') : null,
        accent: accentFor(d.id),
        supportsCheckIn: d.supports_check_in,
        supportsReport: d.supports_report,
        usesDeliverables: d.uses_deliverables,
        checkedIn: d.checked_in,
        // Before any signal the worker is simply engaged — the server has nothing to report yet.
        status: (d.current_status ?? 'engaged') as WorkStatus,
        reportSubmitted: d.report_submitted,
        deliverables: (d.deliverables ?? []).map((x) => ({
          id: x.id,
          title: x.title,
          status: x.status,
          submittedAt: x.submitted_at ?? null,
          rejectReason: x.reject_reason ?? null,
        })),
      };
      this.realWork.add(d.id);
      return detail;
    } catch {
      return null;
    }
  }

  /**
   * Check in at the site (POST /engagements/{id}/check-in) — opens the work session and narrates
   * `arrived`. Best-effort geo: a device without a fix still checks in, since the server accepts
   * null coordinates rather than blocking the worker. A second check-in comes back 409.
   */
  async checkIn(id: string): Promise<MutationResult> {
    if (!this.realWork.has(id)) {
      const w = this.workDetails[id];
      if (w) {
        w.checkedIn = true;
        w.status = 'arrived';
      }
      return { ok: true };
    }
    const fix = await currentPosition();
    return this.attempt(() => this.api.checkIn(id, fix?.latitude, fix?.longitude, fix?.accuracyM));
  }

  /** Check out (POST /engagements/{id}/check-out) — closes the open session with the end geo. */
  async checkOut(id: string): Promise<MutationResult> {
    if (!this.realWork.has(id)) {
      const w = this.workDetails[id];
      if (w) {
        w.checkedIn = false;
      }
      return { ok: true };
    }
    const fix = await currentPosition();
    return this.attempt(() => this.api.checkOut(id, fix?.latitude, fix?.longitude, fix?.accuracyM));
  }

  /** Emit a structured status signal (POST /engagements/{id}/status) — narrated into the timeline. */
  async setWorkStatus(id: string, status: WorkStatus): Promise<MutationResult> {
    if (!this.realWork.has(id)) {
      const w = this.workDetails[id];
      if (w) {
        w.status = status;
      }
      return { ok: true };
    }
    if (status === 'arrived' || status === 'engaged') {
      // `arrived` belongs to check-in and `engaged` is the absence of a signal (P5-06) — neither is
      // postable. The chip list never offers them; this is the belt to that braces.
      return { ok: false };
    }
    return this.attempt(() => this.api.recordStatus(id, status));
  }

  /** Submit the on-site job report (POST /engagements/{id}/report) — summary, materials, photos. */
  async submitReport(id: string, draft: ReportDraft): Promise<MutationResult> {
    if (!this.realWork.has(id)) {
      const w = this.workDetails[id];
      if (w) {
        w.reportSubmitted = true;
        w.status = 'completed';
      }
      return { ok: true };
    }
    return this.attempt(() => this.api.submitJobReport(id, {
      summary: draft.summary,
      extraChargesMinor: draft.extraChargesMinor,
      materials: draft.materials,
      photos: draft.photos,
    }));
  }

  /**
   * Submit a deliverable (POST /engagements/{id}/deliverables) — the REMOTE path's proof of work,
   * the counterpart of the on-site job report.
   */
  async submitDeliverable(id: string, title: string, mediaUrl?: string): Promise<MutationResult> {
    if (!this.realWork.has(id)) {
      const w = this.workDetails[id];
      if (w) {
        w.deliverables = [
          { id: `d${w.deliverables.length + 1}`, title, status: 'submitted', submittedAt: null, rejectReason: null },
          ...w.deliverables,
        ];
      }
      return { ok: true };
    }
    return this.attempt(() => this.api.submitDeliverable(id, title, mediaUrl));
  }

  /**
   * Run one mutation, surfacing the server's problem+json `detail` on failure — a refusal here is
   * usually a real rule (remote check-in, a session already open), and the worker deserves to read
   * it rather than a generic error.
   */
  private async attempt(call: () => Promise<unknown>): Promise<MutationResult> {
    try {
      await call();
      return { ok: true };
    } catch (e) {
      const problem = e as { detail?: unknown; title?: unknown };
      // Some problems carry an empty `detail` and say everything in `title` — prefer whichever is
      // actually populated, and fall through to the caller's generic copy when neither is.
      const text = [problem.detail, problem.title]
        .find((v): v is string => typeof v === 'string' && v.trim() !== '');

      return { ok: false, detail: text };
    }
  }

  /** Decline a lead — drops it from the feed (fixture). */
  declineLead(id: string): void {
    const i = this.leads.findIndex((l) => l.id === id);
    if (i >= 0) {
      this.leads.splice(i, 1);
    }
  }

  /** Submit a quote for a lead — removes it from the open feed (it becomes a pending quote). */
  submitQuote(id: string): void {
    this.declineLead(id);
  }
}
