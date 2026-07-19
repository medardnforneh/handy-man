<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

/**
 * Deliverable lifecycle (doc 06). Submitted by the provider, then accepted or rejected by the
 * customer; a rejected deliverable can be superseded by a new submission.
 */
enum DeliverableStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
