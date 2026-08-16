/**
 * View models for the customer section. These mirror the API contract (see
 * `src/app/api/generated/schema.d.ts`) but stay UI-shaped: the API client maps onto them so the
 * screens never depend on transport details.
 */

export type EngagementMode = 'onsite' | 'remote' | 'hybrid';

export type JobStatus =
  | 'draft' | 'open' | 'offered' | 'engaged' | 'scheduled'
  | 'in_progress' | 'work_submitted' | 'completed' | 'cancelled';

/** Which semantic token an avatar/accent uses — never a literal colour (doc 08). */
export type Accent = 'brand' | 'info' | 'warning' | 'muted';

export interface Provider {
  id: string;
  name: string;
  initials: string;
  skill: string;
  rating: number;
  mode: EngagementMode;
  distanceKm: number | null;
  verified: boolean;
  accent: Accent;
}

/** A specific service (leaf skill) under a category — carries the real skill UUID for job creation. */
export interface SkillLeaf {
  id: string;
  label: string;
}

export interface Category {
  id: string;
  label: string;
  /** Ionicons name. */
  icon: string;
  /** The category's real skill UUID + its leaves, present when loaded from GET /skills. */
  skillId?: string;
  leaves?: SkillLeaf[];
}

/** A published review (double-blind, P6-08) — the private note is never sent to the client. */
export interface ProviderReview {
  id: string;
  authorInitials: string;
  authorName: string;
  rating: number;
  comment: string;
  date: string;
  mode: EngagementMode;
  accent: Accent;
}

/**
 * The public provider profile — mirrors `GET /v1/providers/{party}/reviews` + `/metrics`.
 * `ratingAvg` is the Bayesian-shrunk display rating (P6-09) and is null when unrated; `onTimeRate`
 * is null below the sample-size floor (P6-12) so "100% (1 job)" is never shown.
 */
export interface ProviderProfile {
  id: string;
  name: string;
  initials: string;
  headline: string;
  accent: Accent;
  verified: boolean;
  mode: EngagementMode;
  city: string;
  ratingAvg: number | null;
  ratingCount: number;
  jobsCompleted90d: number;
  onTimeRate: number | null;
  responseTime: string;
  memberSince: string;
  skills: string[];
  about: string;
  reviews: ProviderReview[];
}

export interface JobSummary {
  id: string;
  reference: string;
  title: string;
  status: JobStatus;
  providerName: string | null;
  amountMinor: number;
  milestonesDone: number;
  milestonesTotal: number;
}

/** A saved address the customer can attach to an on-site/hybrid job (never needed for remote). */
export interface SavedAddress {
  id: string;
  label: string;
  line: string;
}

export type MilestoneStatus = 'pending' | 'in_progress' | 'submitted' | 'paid';

/** One milestone in a job's plan — the customer approves a `submitted` one to release its escrow slice. */
export interface MilestoneView {
  id: string;
  title: string;
  amountMinor: number;
  status: MilestoneStatus;
}

/** The full job overview (distinct from the chat workspace): money, milestones, provider, location. */
export interface JobDetail {
  id: string;
  reference: string;
  title: string;
  status: JobStatus;
  mode: EngagementMode;
  providerName: string | null;
  providerInitials: string | null;
  providerId: string | null;
  accent: Accent;
  addressLine: string | null;
  currency: string;
  agreedMinor: number;
  escrowHeldMinor: number;
  releasedMinor: number;
  milestones: MilestoneView[];
  /** The engagement, when one exists — what completing, reviewing and disputing are scoped to. */
  engagementId: string | null;
  /** Set once either party has marked the work finished; opens the review window (P6-08). */
  completedAt: string | null;
  /** Whether THIS user has already reviewed. Says nothing about the other side — reviews are blind. */
  reviewed: boolean;
}

/** One priced line of a received quotation, as the customer reads it. */
export interface QuoteLine {
  label: string;
  kind: 'labour' | 'material' | 'travel' | 'other';
  quantity: number;
  unitPriceMinor: number;
}

/**
 * A quotation the customer has received on their job (P2.5-01).
 *
 * Pre-engagement the provider is a headline and a badge, never a name (P2-03) — which is why this
 * carries `providerHeadline` rather than a person. `expired` is computed here rather than trusted
 * from a status: a quote can lapse while the screen is open, and offering "Accept" on a quote the
 * server will refuse is worse than saying it has run out.
 */
export interface JobQuote {
  id: string;
  version: number;
  status: 'draft' | 'submitted' | 'accepted' | 'rejected' | 'expired' | 'withdrawn' | 'superseded';
  providerPartyId: string;
  providerHeadline: string;
  providerVerified: boolean;
  providerRating: number | null;
  providerRatingCount: number;
  totalMinor: number;
  depositMinor: number;
  balanceMinor: number;
  notes: string | null;
  validUntil: string;
  expired: boolean;
  lines: QuoteLine[];
}

/** What the "post a request" form collects — mirrors CreateJob (doc 06: address only off-remote). */
export interface NewJobInput {
  title: string;
  categoryId: string;
  /** The chosen leaf skill's real UUID (present when categories came from the API) — CreateJob needs a leaf. */
  skillId: string | null;
  mode: EngagementMode;
  addressId: string | null;
  details: string;
  budgetMinor: number | null;
}

export interface ChatSummary {
  /** The JOB id — the workspace route is keyed by job, so this is what `open()` navigates with. */
  id: string;
  /** The conversation id, used to mark the thread read. Null on a fixture row. */
  conversationId: string | null;
  providerName: string;
  initials: string;
  reference: string;
  /** Free-form preview text. Empty when the last message was server-narrated — see `previewKey`. */
  preview: string;
  /**
   * i18n key for a server-narrated last message ("quote accepted", "on the way"). The API sends the
   * KIND, not a sentence, precisely so the row renders in the reader's language rather than the
   * server's.
   */
  previewKey?: string;
  time: string;
  unread: number;
  accent: Accent;
}

export type MessageKind = 'text' | 'voice' | 'system' | 'quote' | 'milestone' | 'deliverable' | 'warranty';

export interface QuotePayload {
  version: number;
  totalMinor: number;
  depositMinor: number;
  balanceMinor: number;
}

export interface MilestonePayload {
  amountMinor: number;
}

/**
 * A warranty the provider has issued on this engagement (P6-11).
 *
 * Like a deliverable, it arrives as a narrated thread event and not through a list endpoint —
 * warranties have no read of their own, so this message IS the customer's copy of it. The id is
 * what a claim is filed against, and `claimed` is a local latch: the server refuses a second claim,
 * so the button goes as soon as one lands.
 */
export interface WarrantyPayload {
  id: string;
  /**
   * When cover ends, already written out in the reader's language.
   *
   * Formatted at the mapping boundary rather than by Angular's `date` pipe: this app registers no
   * Angular locale data, so the pipe renders en-US regardless of the chrome around it.
   */
  expiresOn: string;
  claimed?: boolean;
}

/**
 * A deliverable the provider has submitted for review (P4-08) — the remote path's proof of work.
 *
 * It arrives as a narrated thread event rather than through a list endpoint, because the thread is
 * where the engagement plays out and there is no customer-facing read for deliverables. The id is
 * what `POST /deliverables/{id}/review` needs, and `reviewed` is a local latch: the server 409s a
 * second review, so the buttons go the moment one lands rather than inviting a refusal.
 */

export interface DeliverablePayload {
  id: string;
  title: string;
  reviewed?: 'accepted' | 'rejected';
}

/**
 * One entry in the workspace thread. Structured kinds (`quote`, `milestone`, `system`) are narrated
 * by the SERVER (CLAUDE.md rule #11) — the client only ever posts `text`/`voice`.
 */
export interface WorkspaceMessage {
  id: string;
  kind: MessageKind;
  mine: boolean;
  /** Free-form body (text kind). */
  body?: string;
  time?: string;
  /** Voice note length, e.g. "0:14". */
  duration?: string;
  /** The authorized media route for a voice note's audio — fetched with the Bearer, not set as src. */
  mediaUrl?: string;
  /** i18n key for a system chip, e.g. `workspace.on_the_way`. */
  systemKey?: string;
  quote?: QuotePayload;
  milestone?: MilestonePayload;
  deliverable?: DeliverablePayload;
  warranty?: WarrantyPayload;
  /**
   * Set only on a message this device composed and the server has not confirmed yet (P5-02).
   * Absent means the server has it — which is the state every message reaches once the write queue
   * drains, at which point the optimistic copy is replaced by the real one.
   */
  delivery?: 'queued' | 'failed';
}

export interface WorkspaceThread {
  id: string;
  providerName: string;
  initials: string;
  reference: string;
  skill: string;
  status: JobStatus;
  accent: Accent;
  messages: WorkspaceMessage[];
  /** The live channel's key (`private-engagement.{id}`), or null on a fixture/unengaged thread. */
  engagementId: string | null;
  /** The conversation this thread belongs to — what marks it read. Null on a fixture thread. */
  conversationId: string | null;
}
