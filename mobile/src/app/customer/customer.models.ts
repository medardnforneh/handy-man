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

export type MessageKind = 'text' | 'voice' | 'system' | 'quote' | 'milestone';

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
