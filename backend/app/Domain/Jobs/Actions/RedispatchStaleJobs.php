<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\JobStatus;
use App\Models\Job;
use Illuminate\Support\Facades\DB;

/**
 * The offer-expiry cascade (build plan P8-03). A dispatch job whose offers have all expired without
 * an acceptance — and which has no engagement — is re-dispatched to the next batch of ranked
 * providers. Scheduled after the offer-expiry sweep; idempotent (already-offered providers are
 * skipped inside {@see DispatchJob}).
 */
final class RedispatchStaleJobs
{
    public function __construct(private readonly DispatchJob $dispatch) {}

    /**
     * Re-dispatches every stale dispatch job. Returns the number of new offers sent.
     */
    public function handle(): int
    {
        $jobs = Job::query()
            ->where('assignment_mode', 'dispatch')
            ->where('status', JobStatus::Offered->value)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('engagements')
                ->whereColumn('engagements.job_id', 'service_jobs.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('job_offers')
                ->whereColumn('job_offers.job_id', 'service_jobs.id')
                ->where('job_offers.status', 'pending'))
            ->get();

        $sent = 0;
        foreach ($jobs as $job) {
            $sent += $this->dispatch->handle($job);
        }

        return $sent;
    }
}
