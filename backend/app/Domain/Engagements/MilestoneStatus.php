<?php

declare(strict_types=1);

namespace App\Domain\Engagements;

/**
 * Milestone lifecycle (doc 06). Approval releases that milestone's slice from escrow (Phase 3);
 * position 0 is conventionally the deposit.
 */
enum MilestoneStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';
}
