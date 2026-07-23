<?php

declare(strict_types=1);

namespace App\Domain\Metrics;

use App\Models\Engagement;
use App\Models\ProviderProfile;
use Illuminate\Support\Facades\DB;

/**
 * Computes a provider's performance metrics (build plan P6-12/P6-13, doc 04). Two disciplines:
 *
 *  - **Sample-size floor.** A rate from fewer than the floor of data points is NOT surfaced — a
 *    "100% on-time (1 job)" would mislead. Such a rate is returned as null.
 *  - **Leakage is a flag, not an accusation.** A provider with many completions but few repeat
 *    customers *might* be taking business off-platform — so it's flagged for a human, never acted on
 *    automatically (P6-13). Surfaced to admins only.
 *
 * @phpstan-type Metrics array{jobs_completed_90d: int, rating_avg: float|null, rating_count: int, on_time_rate: float|null, on_time_sample: int, repeat_customer_rate: float|null, completed_total: int, leakage_flag: bool}
 */
final class ProviderMetrics
{
    /**
     * @return Metrics
     */
    public function forParty(string $partyId): array
    {
        $floor = (int) config('metrics.sample_floor', 5);
        $windowDays = (int) config('metrics.window_days', 90);

        $completed90d = Engagement::query()
            ->where('provider_party_id', $partyId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays($windowDays))
            ->count();

        [$onTimeSample, $onTimeHits] = $this->onTime($partyId, $windowDays);
        $onTimeRate = $onTimeSample >= $floor ? round($onTimeHits / $onTimeSample, 4) : null;

        [$completedTotal, $distinctCustomers] = $this->repeatBasis($partyId);
        $repeatRate = $completedTotal >= $floor
            ? round(($completedTotal - $distinctCustomers) / $completedTotal, 4)
            : null;

        $profile = ProviderProfile::query()->where('party_id', $partyId)->first();

        return [
            'jobs_completed_90d' => $completed90d,
            'rating_avg' => $profile?->rating_avg !== null ? (float) $profile->rating_avg : null,
            'rating_count' => (int) ($profile?->rating_count ?? 0),
            'on_time_rate' => $onTimeRate,
            'on_time_sample' => $onTimeSample,
            'repeat_customer_rate' => $repeatRate,
            'completed_total' => $completedTotal,
            'leakage_flag' => $this->isLeakageFlagged($completedTotal, $repeatRate),
        ];
    }

    /**
     * On-time = a booked assignment (has scheduled_to) whose work session ended on or before it.
     * Returns [sample, hits].
     *
     * @return array{0: int, 1: int}
     */
    private function onTime(string $partyId, int $windowDays): array
    {
        /** @var object{sample: int, hits: int}|null $row */
        $row = DB::table('work_sessions as ws')
            ->join('assignments as a', 'a.id', '=', 'ws.assignment_id')
            ->join('engagements as e', 'e.id', '=', 'a.engagement_id')
            ->where('e.provider_party_id', $partyId)
            ->whereNotNull('ws.ended_at')
            ->whereNotNull('a.scheduled_to')
            ->where('ws.ended_at', '>=', now()->subDays($windowDays))
            ->selectRaw('count(*) as sample, count(*) filter (where ws.ended_at <= a.scheduled_to) as hits')
            ->first();

        return [(int) ($row->sample ?? 0), (int) ($row->hits ?? 0)];
    }

    /**
     * Completed-engagement basis for the repeat rate: total completions and distinct customers.
     *
     * @return array{0: int, 1: int}
     */
    private function repeatBasis(string $partyId): array
    {
        /** @var object{total: int, distinct_customers: int}|null $row */
        $row = DB::table('engagements as e')
            ->join('service_jobs as j', 'j.id', '=', 'e.job_id')
            ->where('e.provider_party_id', $partyId)
            ->whereNotNull('e.completed_at')
            ->selectRaw('count(*) as total, count(distinct j.customer_party_id) as distinct_customers')
            ->first();

        return [(int) ($row->total ?? 0), (int) ($row->distinct_customers ?? 0)];
    }

    private function isLeakageFlagged(int $completedTotal, ?float $repeatRate): bool
    {
        $minCompleted = (int) config('metrics.leakage_min_completed', 8);
        $threshold = (float) config('metrics.leakage_repeat_threshold', 0.15);

        return $completedTotal >= $minCompleted
            && $repeatRate !== null
            && $repeatRate < $threshold;
    }
}
