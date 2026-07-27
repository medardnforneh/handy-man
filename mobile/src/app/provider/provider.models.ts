/**
 * View models for the provider section ("Offer services"). These mirror the API contract but stay
 * UI-shaped, like the customer models. Shared enums (EngagementMode, Accent, JobStatus) are reused
 * from the customer models so the two sections never drift.
 */
import { Accent, EngagementMode, JobStatus } from '../customer/customer.models';

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
  id: string;
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
export type WorkStatus = 'engaged' | 'on_the_way' | 'arrived' | 'started' | 'paused' | 'completed';

/** The provider's execution view of one job: check-in, status, and report submission (P5-03/04/06). */
export interface WorkDetail {
  id: string;
  reference: string;
  title: string;
  customerName: string;
  customerInitials: string;
  mode: EngagementMode;
  addressLine: string | null;
  accent: Accent;
  checkedIn: boolean;
  status: WorkStatus;
  reportSubmitted: boolean;
}

/** A line in the quote the provider composes for a lead. */
export interface QuoteLine {
  id: string;
  label: string;
  amountMinor: number;
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
