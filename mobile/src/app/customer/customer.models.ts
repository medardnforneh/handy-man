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

export interface Category {
  id: string;
  label: string;
  /** Ionicons name. */
  icon: string;
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

export interface ChatSummary {
  id: string;
  providerName: string;
  initials: string;
  reference: string;
  preview: string;
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
  /** i18n key for a system chip, e.g. `workspace.on_the_way`. */
  systemKey?: string;
  quote?: QuotePayload;
  milestone?: MilestonePayload;
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
}
