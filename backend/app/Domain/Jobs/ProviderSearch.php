<?php

declare(strict_types=1);

namespace App\Domain\Jobs;

use App\Models\Block;
use App\Models\Job;
use App\Models\ProviderProfile;
use App\Models\ServiceArea;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds providers for a job (build plan P2-04). Matching = skill + availability + (for physical
 * work) service-area coverage, ranked by verification tier then rating.
 *
 * Crucially, geography is SKIPPED for remote work (doc 06): a remote job draws from the whole
 * skilled pool regardless of where anyone is. Whether to apply the geo filter is decided by the
 * EngagementModePolicy — never an inline mode check.
 */
final class ProviderSearch
{
    public function __construct(
        private readonly EngagementModePolicy $modePolicy,
    ) {}

    /**
     * @return Collection<int, ProviderProfile>
     */
    public function forJob(Job $job, int $limit = 50): Collection
    {
        $query = ProviderProfile::query()
            ->whereNull('suspended_at')
            ->where('accepts_direct', true)
            // Don't match a party to its own job.
            ->where('party_id', '!=', $job->customer_party_id)
            // Skill match.
            ->whereHas('skills', fn (Builder $q): Builder => $q->where('skill_id', $job->skill_id));

        // A block is honoured in search + ranking (P6-07): a provider blocked by, or blocking, the
        // customer never appears — the same boundary the offer path enforces.
        $blocked = Block::partyIdsAround($job->customer_party_id);
        if ($blocked !== []) {
            $query->whereNotIn('party_id', $blocked);
        }

        if ($job->requires_verified_provider) {
            $query->where('verification_tier', '>=', 2);
        }

        // Geo filter ONLY for modes that use dispatch/coverage (onsite/hybrid) with a known point.
        if ($this->modePolicy->supportsDispatch($job->engagement_mode) && $job->address_id !== null) {
            $point = $job->address->point;
            $query->whereHas('serviceAreas', function (Builder $q) use ($point): void {
                /** @var Builder<ServiceArea> $q */
                $q->covering($point->latitude, $point->longitude);
            });
        }

        return $query
            ->orderByDesc('verification_tier')
            ->orderByDesc('rating_avg')
            ->limit($limit)
            ->get();
    }
}
