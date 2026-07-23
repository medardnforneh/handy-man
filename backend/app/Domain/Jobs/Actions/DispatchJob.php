<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\ProviderSearch;
use App\Domain\Offers\Actions\CreateDirectOffer;
use App\Domain\Safety\PartyBlocked;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\ProviderProfile;

/**
 * Dispatch-mode fan-out (build plan P8-03). Ranks providers for the job (skill + coverage + tier +
 * rating via {@see ProviderSearch}) and offers to the top N not already offered — the platform picks,
 * the customer doesn't. Enabled only where supply density supports it (the `dispatch` flag). Re-running
 * after an expiry cascade offers the NEXT batch, since already-offered providers are skipped.
 */
final class DispatchJob
{
    public function __construct(
        private readonly ProviderSearch $search,
        private readonly CreateDirectOffer $offer,
    ) {}

    /**
     * Fans out to the next batch of top-ranked providers. Returns how many offers were sent.
     */
    public function handle(Job $job, ?int $limit = null): int
    {
        $limit ??= (int) config('marketplace.dispatch_fanout', 5);

        /** @var array<int, string> $alreadyOffered */
        $alreadyOffered = JobOffer::query()->where('job_id', $job->id)->pluck('provider_party_id')->all();

        $candidates = $this->search->forJob($job, 50)
            ->reject(fn (ProviderProfile $p): bool => in_array($p->party_id, $alreadyOffered, true))
            ->take($limit);

        $sent = 0;
        foreach ($candidates as $provider) {
            try {
                $this->offer->handle($job, $provider->party_id, null, 'Dispatch offer');
                $sent++;
            } catch (PartyBlocked) {
                // A block is honoured — skip and keep dispatching to the others.
            }
        }

        return $sent;
    }
}
