<?php

declare(strict_types=1);

namespace App\Domain\Metrics;

use App\Models\Job;
use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;

/**
 * Marketplace health for admins (build plan P8-06, doc 05): liquidity (are jobs finding providers?),
 * match rate (are they converting?), time-to-offer (how fast?), and a leakage proxy count. Computed
 * over a rolling window from the models — the numbers a founder watches to decide when to turn on
 * dispatch/bidding (doc 01 §4).
 *
 * @phpstan-type Summary array{jobs: int, offered_rate: float, match_rate: float, avg_time_to_offer_seconds: int|null, active_providers: int, leakage_flagged: int}
 */
final class MarketplaceAnalytics
{
    /**
     * @return Summary
     */
    public function summary(int $windowDays = 30): array
    {
        $since = now()->subDays($windowDays);

        $jobs = Job::query()->where('created_at', '>=', $since)->count();

        $offered = Job::query()
            ->where('created_at', '>=', $since)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('job_offers')->whereColumn('job_offers.job_id', 'service_jobs.id'))
            ->count();

        $engaged = Job::query()
            ->where('created_at', '>=', $since)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('engagements')->whereColumn('engagements.job_id', 'service_jobs.id'))
            ->count();

        return [
            'jobs' => $jobs,
            // Liquidity: share of jobs that drew at least one offer.
            'offered_rate' => $jobs > 0 ? round($offered / $jobs, 4) : 0.0,
            // Match rate: share of jobs that converted to an engagement.
            'match_rate' => $jobs > 0 ? round($engaged / $jobs, 4) : 0.0,
            'avg_time_to_offer_seconds' => $this->avgTimeToOffer($since),
            'active_providers' => ProviderProfile::query()->whereNull('suspended_at')->count(),
            'leakage_flagged' => $this->leakageFlagged(),
        ];
    }

    private function avgTimeToOffer(\DateTimeInterface $since): ?int
    {
        $avg = DB::table('service_jobs as j')
            ->join('job_offers as o', 'o.job_id', '=', 'j.id')
            ->whereNotNull('j.published_at')
            ->where('j.created_at', '>=', $since)
            ->selectRaw('avg(extract(epoch from (o.created_at - j.published_at))) as seconds')
            ->value('seconds');

        return $avg !== null ? (int) round((float) $avg) : null;
    }

    private function leakageFlagged(): int
    {
        $completed = '(select count(*) from engagements e where e.provider_party_id = provider_profiles.party_id and e.completed_at is not null)';
        $distinct = '(select count(distinct j.customer_party_id) from engagements e join service_jobs j on j.id = e.job_id where e.provider_party_id = provider_profiles.party_id and e.completed_at is not null)';
        $minCompleted = (int) config('metrics.leakage_min_completed', 8);
        $threshold = (float) config('metrics.leakage_repeat_threshold', 0.15);

        return ProviderProfile::query()
            ->whereRaw("{$completed} >= ?", [$minCompleted])
            ->whereRaw("({$completed} - {$distinct}) < ?::numeric * {$completed}", [$threshold])
            ->count();
    }
}
