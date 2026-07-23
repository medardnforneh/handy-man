<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

/**
 * A follow-up's lifecycle (doc 07). `suppressed` is a first-class product signal — routinely
 * suppressing means the app is sending too much.
 */
enum FollowUpStatus: string
{
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
    case Responded = 'responded';
    case Failed = 'failed';
    case Suppressed = 'suppressed';
}
