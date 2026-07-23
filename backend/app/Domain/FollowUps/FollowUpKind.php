<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

/**
 * The kind of follow-up (doc 07). Each carries two policy bits that the dispatcher reads:
 *
 *  - {@see isTransactional()} — service-delivery messages (a panic-adjacent alert, an auto-approve
 *    warning, "your payout is ready") bypass the channel budget entirely.
 *  - {@see requiresMarketingConsent()} — re-engagement and maintenance nudges are marketing; under
 *    Law No. 2024/017 they may only be sent with a `marketing` consent grant (doc 04), honoured on
 *    revocation immediately.
 */
enum FollowUpKind: string
{
    case QuotePendingCustomer = 'quote_pending_customer';
    case QuoteExpiring = 'quote_expiring';
    case JobUnquoted = 'job_unquoted';
    case SiteVisitReminder = 'site_visit_reminder';
    case JobStartingSoon = 'job_starting_soon';
    case CheckInOverdue = 'check_in_overdue';
    case AwaitingApproval = 'awaiting_approval';
    case AutoApproveWarning = 'auto_approve_warning';
    case ReviewRequest = 'review_request';
    case ReviewReminder = 'review_reminder';
    case PaymentDue = 'payment_due';
    case PayoutReady = 'payout_ready';
    case WarrantyExpiring = 'warranty_expiring';
    case MaintenanceDue = 'maintenance_due';
    case Reengagement = 'reengagement';
    case AbandonedDraft = 'abandoned_draft';

    /**
     * Transactional (service-delivery) kinds bypass the channel budget — they must always get through.
     */
    public function isTransactional(): bool
    {
        return match ($this) {
            self::CheckInOverdue, self::AutoApproveWarning, self::PayoutReady,
            self::AwaitingApproval, self::PaymentDue => true,
            default => false,
        };
    }

    /**
     * Marketing-ish kinds require a `marketing` consent grant and never bypass the budget.
     */
    public function requiresMarketingConsent(): bool
    {
        return match ($this) {
            self::Reengagement, self::MaintenanceDue => true,
            default => false,
        };
    }
}
