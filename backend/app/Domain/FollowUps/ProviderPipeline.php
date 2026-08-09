<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The provider's work funnel (build plan P7-08, the "pipeline" half of the CRM surface).
 *
 * Four stages, each one a COUNT and a VALUE read straight off the rows that already exist — no new
 * concept, no stored stage, nothing a provider has to maintain by hand:
 *
 *   - `leads`     — offers sent to them and still awaiting an answer (`job_offers.status = pending`)
 *   - `quoted`    — quotes they have submitted and the customer has not yet decided on
 *                   (`quotations.status = submitted`)
 *   - `engaged`   — work won and still in flight (`engagements.completed_at IS NULL`)
 *   - `completed` — work finished inside the rolling window
 *
 * A stage's value is the money that stage can honestly claim: an offer's `amount_minor` where the
 * customer named one, else the job's budget, and null-safe throughout — a lead with no stated budget
 * contributes to the COUNT but not to the value, because inventing a number for it would make the
 * funnel lie in the direction a provider most wants to believe.
 *
 * Deliberately NOT a forecast: nothing here is weighted by a probability of closing. The platform
 * knows what was offered, quoted, won and finished; it does not know what will close, and a
 * confident-looking projection built on made-up conversion rates is worse than four honest counts.
 */
final class ProviderPipeline
{
    /**
     * @return array{
     *     currency: string,
     *     window_days: int,
     *     stages: list<array{stage: string, count: int, value_minor: int}>
     * }
     */
    public function forProvider(string $providerPartyId): array
    {
        $windowDays = (int) config('metrics.window_days', 90);
        $since = now()->subDays($windowDays);

        // Offers still awaiting this provider's answer. Value falls back to the job's budget, which
        // is what the customer themselves put on the work when no offer amount was named.
        $leads = DB::table('job_offers as o')
            ->join('service_jobs as j', 'j.id', '=', 'o.job_id')
            ->where('o.provider_party_id', $providerPartyId)
            ->where('o.status', 'pending')
            ->selectRaw('count(*) as n, coalesce(sum(coalesce(o.amount_minor, j.budget_minor, 0)), 0) as v')
            ->first();

        // Quotes out with the customer. `submitted` is the only live state — draft is not yet the
        // customer's to see, and every other state has already resolved one way or the other.
        $quoted = DB::table('quotations')
            ->where('provider_party_id', $providerPartyId)
            ->where('status', 'submitted')
            ->selectRaw('count(*) as n, coalesce(sum(subtotal_minor), 0) as v')
            ->first();

        $engaged = DB::table('engagements')
            ->where('provider_party_id', $providerPartyId)
            ->whereNull('completed_at')
            ->selectRaw('count(*) as n, coalesce(sum(agreed_amount_minor), 0) as v')
            ->first();

        // Completed is windowed; the others are live state and are not, because "you have 3 offers
        // waiting" is true regardless of when they arrived.
        $completed = DB::table('engagements')
            ->where('provider_party_id', $providerPartyId)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $since)
            ->selectRaw('count(*) as n, coalesce(sum(agreed_amount_minor), 0) as v')
            ->first();

        return [
            'currency' => Money::XAF,
            'window_days' => $windowDays,
            'stages' => [
                self::stage('leads', $leads),
                self::stage('quoted', $quoted),
                self::stage('engaged', $engaged),
                self::stage('completed', $completed),
            ],
        ];
    }

    /**
     * @return array{stage: string, count: int, value_minor: int}
     */
    private static function stage(string $name, ?object $row): array
    {
        return [
            'stage' => $name,
            'count' => (int) ($row->n ?? 0),
            'value_minor' => (int) ($row->v ?? 0),
        ];
    }
}
