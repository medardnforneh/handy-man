<?php

declare(strict_types=1);

namespace App\Domain\Verification;

/**
 * The review state of a verification document (doc 04). A document is `pending` until a human admin
 * approves or rejects it; `expired` is for a document past its `expires_at` (e.g. a lapsed licence).
 */
enum DocStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
