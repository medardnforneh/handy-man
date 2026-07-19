<?php

declare(strict_types=1);

namespace App\Domain\Quotations;

/**
 * Site-visit lifecycle (doc 06). A chargeable, `Completed` visit linked to the accepted quotation
 * has its fee credited against the engagement.
 */
enum SiteVisitStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
}
