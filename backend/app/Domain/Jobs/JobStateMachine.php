<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Models\Job;

/**
 * The job lifecycle gatekeeper (CLAUDE.md rule #8). Every status change goes through here; illegal
 * transitions throw. The structured chat message for a transition is emitted by the Action that
 * calls this, in the same transaction, via the outbox (rule #11) — not here.
 */
final class JobStateMachine
{
    /**
     * Allowed target states per current state.
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'draft' => ['open', 'cancelled'],
        'open' => ['offered', 'engaged', 'cancelled'], // engaged directly when a quote is accepted (P2.5-05)
        'offered' => ['engaged', 'open', 'cancelled'], // back to open if all offers lapse
        'engaged' => ['scheduled', 'in_progress', 'cancelled', 'disputed'],
        'scheduled' => ['en_route', 'in_progress', 'cancelled', 'disputed'],
        'en_route' => ['in_progress', 'cancelled', 'disputed'],
        'in_progress' => ['work_submitted', 'disputed'],
        'work_submitted' => ['completed', 'in_progress', 'disputed'], // rejected → back to in_progress
        'completed' => ['closed', 'disputed'],
        'disputed' => ['in_progress', 'completed', 'closed', 'cancelled'],
        'cancelled' => [], // terminal
        'closed' => [],    // terminal
    ];

    public function canTransition(JobStatus $from, JobStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    /**
     * @return list<JobStatus>
     */
    public function allowedFrom(JobStatus $from): array
    {
        return array_map(fn (string $s): JobStatus => JobStatus::from($s), self::TRANSITIONS[$from->value]);
    }

    /**
     * Apply a transition, or throw. Persists the new status and stamps the relevant timestamp.
     */
    public function transition(Job $job, JobStatus $to): Job
    {
        if (! $this->canTransition($job->status, $to)) {
            throw new IllegalJobTransition($job->status, $to);
        }

        $job->status = $to;

        if ($to === JobStatus::Open && $job->published_at === null) {
            $job->published_at = now();
        }
        if ($to === JobStatus::Cancelled) {
            $job->cancelled_at = now();
        }

        $job->save();

        return $job;
    }
}
