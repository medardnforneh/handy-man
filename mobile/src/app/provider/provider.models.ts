/**
 * View models for the provider section ("Offer services"). These mirror the API contract but stay
 * UI-shaped, like the customer models. Shared enums (EngagementMode, Accent, JobStatus) are reused
 * from the customer models so the two sections never drift.
 */
import { Accent, EngagementMode, JobStatus } from '../customer/customer.models';

/**
 * Who the provider is, as the Home and Profile screens render them. `partyId` is the handle the
 * public metrics/reviews endpoints take — distinct from the provider_profiles row id (P1-08).
 * `serviceAreaRadiusKm` is null when no area is set; there is no stored city, so the label is a
 * radius rather than an invented place name.
 */
export interface ProviderIdentity {
  partyId: string | null;
  name: string;
  initials: string;
  headline: string;
  verificationTier: number;
  rating: number | null;
  ratingCount: number;
  skills: string[];
  serviceAreaRadiusKm: number | null;
}

/** The provider's money at a glance — payable balance and any payout in flight (P3-08). */
export interface ProviderWallet {
  availableMinor: number;
  pendingPayoutMinor: number;
  currency: string;
}

/** Display-safe reputation + workload (rating/on-time null below the sample floor, P6-12). */
export interface ProviderStats {
  activeJobs: number;
  rating: number | null;
  completed90d: number;
  onTimeRate: number | null;
}

/** An open opportunity a provider can respond to (a dispatched job or direct request). */
export interface Lead {
  /** The OFFER id — what `POST /offers/{offer}/accept` takes. */
  id: string;
  /** The offered JOB's id — what `POST /jobs/{job}/quotations` takes. Null on a fixture lead. */
  jobId: string | null;
  reference: string;
  title: string;
  skill: string;
  mode: EngagementMode;
  area: string;
  budgetMinor: number | null;
  postedAgo: string;
  accent: Accent;
  details: string;
}

/** A job the provider is actively working. */
export interface ActiveWork {
  id: string;
  reference: string;
  title: string;
  customerName: string;
  status: JobStatus;
  mode: EngagementMode;
  accent: Accent;
}

/** The provider's live status on a job (mirrors ProviderStatus / P5-06; `arrived` set by check-in). */
export type WorkStatus =
  | 'engaged' | 'on_the_way' | 'arrived' | 'started' | 'paused' | 'resumed' | 'completed';

/**
 * The provider's execution view of one job: check-in, status, and report submission (P5-03/04/06).
 * `supportsCheckIn`, `checkedIn`, `status` and `reportSubmitted` come from the server, derived from
 * the same rows the actions write — the client renders only affordances the server would accept.
 */
export interface WorkDetail {
  id: string;
  reference: string;
  title: string;
  customerName: string;
  customerInitials: string;
  mode: EngagementMode;
  addressLine: string | null;
  accent: Accent;
  supportsCheckIn: boolean;
  /** Whether this mode proves work with an on-site report (false for remote). */
  supportsReport: boolean;
  /** Whether this mode proves work with deliverables (true for remote and hybrid). */
  usesDeliverables: boolean;
  checkedIn: boolean;
  status: WorkStatus;
  reportSubmitted: boolean;
  deliverables: Deliverable[];
}

/** The remote path's proof of work (P4-08) — the customer accepts or rejects each one. */
export interface Deliverable {
  id: string;
  title: string;
  status: 'pending' | 'submitted' | 'accepted' | 'rejected';
  submittedAt: string | null;
  rejectReason: string | null;
}

/** One materials line on a job report — a label, a quantity, and a per-unit price. */
export interface ReportMaterial {
  label: string;
  qty: number;
  unitCostMinor: number;
}

/** What the provider composes on the report sheet before submitting (P5-04). */
export interface ReportDraft {
  summary: string;
  materials: ReportMaterial[];
  extraChargesMinor: number;
  photos: { file: File; kind: 'before' | 'after' }[];
}

/** A line in the quote the provider composes for a lead. */
/** The kinds a quotation line can take (mirrors the server's QuoteLineKind, doc 06). */
export type QuoteLineKind = 'labour' | 'material' | 'travel' | 'other';

/**
 * One line of the quote the provider composes. The server computes the subtotal from these — a
 * client-supplied total is never trusted (P2.5-01), so the screen's total is a PREVIEW of the same
 * arithmetic, not an input.
 */
export interface QuoteLine {
  id: string;
  kind: QuoteLineKind;
  label: string;
  quantity: number;
  unitPriceMinor: number;
}

/**
 * A quotation ready to submit (P2.5-01). `validUntil` is required by the API and must be in the
 * future; the deposit is what gets captured into escrow the moment the customer accepts (P3-13).
 */
export interface QuoteDraft {
  lines: QuoteLine[];
  depositMinor: number;
  notes: string;
  /** ISO date (yyyy-mm-dd) the quote stops being valid. */
  validUntil: string;
}

export type PayoutStatus = 'paid' | 'pending' | 'failed';

/** A settlement to the provider's mobile-money account (P3-08). */
export interface Payout {
  id: string;
  reference: string;
  amountMinor: number;
  status: PayoutStatus;
  date: string;
}
