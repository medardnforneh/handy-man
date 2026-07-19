<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

/**
 * The job lifecycle (doc 02 `job_status`). Every transition goes through JobStateMachine — a
 * controller never sets `$job->status = 'x'` (CLAUDE.md rule #8). Illegal transitions throw.
 */
enum JobStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Offered = 'offered';
    case Engaged = 'engaged';
    case Scheduled = 'scheduled';
    case EnRoute = 'en_route';
    case InProgress = 'in_progress';
    case WorkSubmitted = 'work_submitted';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Disputed = 'disputed';
    case Closed = 'closed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Closed], true);
    }
}
