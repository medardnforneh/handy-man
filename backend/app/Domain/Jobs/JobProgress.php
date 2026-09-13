<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Models\Job;

/**
 * Moves a job's status forward as a consequence of what actually happened to it.
 *
 * The lifecycle (engaged → en_route → in_progress → work_submitted → completed) was defined in the
 * state machine from the start, and the events that should drive it — a provider setting off,
 * checking in, submitting work, a customer approving — all existed as narrated workspace messages.
 * What did not exist was anything moving the job's own `status`, so a finished job stayed `engaged`
 * for ever: still in the provider's active-work list, still "engaged" on the customer's list, and
 * invisible to the admin as completed. The end-to-end remote walk-through (launch checklist) found
 * it.
 *
 * This is the one place that translates an event into a job transition. It only ever uses the
 * state machine, so nothing illegal can happen here; what it adds is the WALK: a customer who
 * approves a job the provider never formally submitted is the strongest evidence there is, and the
 * unreported intermediate states are stepped through rather than refused. A signal that arrives
 * after the job is already past its target is a no-op, and a job in a terminal or disputed state is
 * left alone — those are decided elsewhere.
 */
final class JobProgress
{
    /** The forward path a job walks while it is being worked. */
    private const PATH = [
        JobStatus::Engaged,
        JobStatus::Scheduled,
        JobStatus::EnRoute,
        JobStatus::InProgress,
        JobStatus::WorkSubmitted,
        JobStatus::Completed,
    ];

    public function __construct(private readonly JobStateMachine $stateMachine) {}

    /**
     * Advance the job to $target, stepping through whatever intermediate states the state machine
     * requires. Idempotent: a job already at or beyond the target is untouched.
     */
    public function advanceTo(Job $job, JobStatus $target): Job
    {
        $current = array_search($job->status, self::PATH, true);
        $goal = array_search($target, self::PATH, true);
        if ($current === false || $goal === false || $current >= $goal) {
            return $job;
        }

        while ($job->status !== $target) {
            $next = $this->nextStepToward($job->status, $target);
            if ($next === null) {
                return $job; // no legal route from here — leave it for the state that decides
            }
            $this->stateMachine->transition($job, $next);
        }

        return $job;
    }

    /** Work was sent back: the job is being worked again. */
    public function reopen(Job $job): Job
    {
        if ($job->status === JobStatus::WorkSubmitted) {
            $this->stateMachine->transition($job, JobStatus::InProgress);
        }

        return $job;
    }

    /** A dispute was raised: the job is frozen in `disputed` until a person decides it. */
    public function dispute(Job $job): Job
    {
        if ($job->status !== JobStatus::Disputed && $this->stateMachine->canTransition($job->status, JobStatus::Disputed)) {
            $this->stateMachine->transition($job, JobStatus::Disputed);
        }

        return $job;
    }

    /**
     * The dispute was decided. Where the job goes depends on whether the work was ever finished:
     * a completed engagement's job is completed; anything else is back in progress.
     */
    public function settle(Job $job, bool $engagementCompleted): Job
    {
        if ($job->status !== JobStatus::Disputed) {
            return $job;
        }

        $this->stateMachine->transition($job, $engagementCompleted ? JobStatus::Completed : JobStatus::InProgress);

        return $job;
    }

    /**
     * The furthest path state, up to the target, that is a legal transition from $from. Prefers
     * the biggest legal jump so a job goes engaged → in_progress directly rather than inventing an
     * "en route" it was never told about.
     */
    private function nextStepToward(JobStatus $from, JobStatus $target): ?JobStatus
    {
        $goal = array_search($target, self::PATH, true);
        for ($i = $goal; $i > array_search($from, self::PATH, true); $i--) {
            if ($this->stateMachine->canTransition($from, self::PATH[$i])) {
                return self::PATH[$i];
            }
        }

        return null;
    }
}
