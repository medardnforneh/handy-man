<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\JobStateMachine;
use App\Domain\Jobs\JobStatus;
use App\Models\Job;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Publishes a draft job (draft → open) so it can receive offers (build plan P2-03). The transition
 * goes through the state machine; the announcement rides the outbox in the same transaction.
 */
final class PublishJob
{
    public function __construct(
        private readonly JobStateMachine $stateMachine,
        private readonly Outbox $outbox,
    ) {}

    public function handle(Job $job): Job
    {
        return DB::transaction(function () use ($job): Job {
            $this->stateMachine->transition($job, JobStatus::Open);
            $this->outbox->publish('job.published', ['job_id' => $job->id]);

            return $job;
        });
    }
}
