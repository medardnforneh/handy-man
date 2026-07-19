<?php

declare(strict_types=1);

namespace App\Domain\Engagements;

enum AssignmentStatus: string
{
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case EnRoute = 'en_route';
    case OnSite = 'on_site';
    case Completed = 'completed';
    case Removed = 'removed';
}
