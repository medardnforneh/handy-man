<?php

declare(strict_types=1);

namespace App\Domain\Offers;

/**
 * Offer lifecycle (doc 02). Exactly one `accepted` offer per job is enforced by a partial unique
 * index — the DB guarantee behind AcceptOfferAction's concurrency safety (P2-06).
 */
enum OfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case Superseded = 'superseded';
}
