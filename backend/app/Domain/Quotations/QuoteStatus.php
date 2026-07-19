<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

/**
 * Quotation lifecycle (doc 06). A quote's TERMS are immutable once it leaves `Draft`; a revision is a
 * new version with `supersedes_id`, not an edit (CLAUDE.md rule #9). The DB enforces both.
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';
    case Superseded = 'superseded';

    public function isLive(): bool
    {
        return $this === self::Draft || $this === self::Submitted;
    }
}
