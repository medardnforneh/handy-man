<?php

declare(strict_types=1);

namespace App\Domain\Metrics;

use App\Domain\Money\Ledger;
use App\Models\Dispute;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Milestone;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * The other side of {@see ProviderMetrics}: how a customer has actually behaved.
 *
 * Staff-only, and unlike the provider figures there is no client endpoint mirroring these — nobody
 * is shown their own "hire rate", and nobody should be. These exist to answer the questions support
 * is asked about a customer, of which two matter most:
 *
 *  - **Do they hire?** Leads cost providers real credits (P8-02). Someone who posts request after
 *    request and never engages anyone is spending other people's money, and that shows up as
 *    provider complaints long before anyone connects it to one account.
 *  - **Do they pay, and do they fight?** Money released versus still held, and how often they open a
 *    dispute, are the two facts behind almost every escalation.
 *
 * The same sample-size discipline as the provider side: a rate computed from a handful of jobs is
 * returned as null rather than printed. "0% hire rate" from one unlucky request describes nothing,
 * and staff act on what they are shown.
 *
 * @phpstan-type Metrics array{jobs_posted: int, jobs_posted_90d: int, jobs_hired: int, jobs_cancelled: int, jobs_awaiting: int, in_flight: int, completed: int, hire_rate: float|null, hire_sample: int, agreed_minor: int, released_minor: int, escrow_held_minor: int, disputes_raised: int, disputes_open: int, reports_filed: int, reports_against: int, reviews_left: int, reviews_due: int, rating_given_avg: float|null, providers_used: int, repeat_providers: int, last_activity_at: string|null}
 */
final class CustomerMetrics
{
    /**
     * @return Metrics
     */
    public function forParty(string $partyId): array
    {
        $floor = (int) config('metrics.sample_floor', 5);
        $windowDays = (int) config('metrics.window_days', 90);

        // Drafts are not requests. A draft has never been seen by a provider, cost nobody a credit
        // and committed the customer to nothing, so counting it would inflate every ratio below.
        $posted = Job::query()->where('customer_party_id', $partyId)->where('status', '!=', 'draft');

        $jobsPosted = (clone $posted)->count();
        $jobsPosted90d = (clone $posted)->where('created_at', '>=', now()->subDays($windowDays))->count();
        $jobsCancelled = (clone $posted)->where('status', 'cancelled')->count();

        // Still looking for someone: the requests providers are currently being charged to see.
        $jobsAwaiting = (clone $posted)->whereIn('status', ['open', 'offered'])->count();

        $engagements = Engagement::query()->whereIn(
            'job_id',
            Job::query()->where('customer_party_id', $partyId)->select('id')
        );

        $jobsHired = (clone $engagements)->distinct()->count('job_id');
        $completed = (clone $engagements)->whereNotNull('completed_at')->count();
        $inFlight = (clone $engagements)->whereNull('completed_at')->count();

        // Cancelled requests are excluded from the denominator: a customer who cancels because the
        // pipe stopped leaking has not failed to hire anyone, and counting it as a miss would
        // penalise honesty. What remains is requests that ran their course.
        $hireSample = $jobsPosted - $jobsCancelled - $jobsAwaiting;
        $hireRate = $hireSample >= $floor ? round($jobsHired / $hireSample, 4) : null;

        $engagementIds = (clone $engagements)->select('id');

        // Agreed is what was promised; released is what actually reached a provider. Both, because
        // the gap between them IS the answer when a provider calls to ask where their money is.
        $agreed = (int) (clone $engagements)->sum('agreed_amount_minor');
        $released = (int) Milestone::query()
            ->whereIn('engagement_id', $engagementIds)
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount_minor');

        $providers = (clone $engagements)
            ->select('provider_party_id', DB::raw('count(*) as n'))
            ->groupBy('provider_party_id')
            ->pluck('n', 'provider_party_id');

        return [
            'jobs_posted' => $jobsPosted,
            'jobs_posted_90d' => $jobsPosted90d,
            'jobs_hired' => $jobsHired,
            'jobs_cancelled' => $jobsCancelled,
            'jobs_awaiting' => $jobsAwaiting,
            'in_flight' => $inFlight,
            'completed' => $completed,
            'hire_rate' => $hireRate,
            'hire_sample' => max($hireSample, 0),
            'agreed_minor' => $agreed,
            'released_minor' => $released,
            'escrow_held_minor' => $this->escrowHeld($partyId),
            'disputes_raised' => Dispute::query()->where('raised_by_party_id', $partyId)->count(),
            'disputes_open' => Dispute::query()
                ->where('raised_by_party_id', $partyId)
                ->whereIn('status', ['open', 'under_review'])
                ->count(),
            'reports_filed' => Report::query()->where('reporter_party_id', $partyId)->count(),
            'reports_against' => Report::query()->where('subject_party_id', $partyId)->count(),
            'reviews_left' => Review::query()->where('author_party_id', $partyId)->whereNotNull('submitted_at')->count(),
            'reviews_due' => $completed,
            'rating_given_avg' => $this->ratingGiven($partyId, $floor),
            'providers_used' => $providers->count(),
            'repeat_providers' => $providers->filter(fn (int $n): bool => $n > 1)->count(),
            'last_activity_at' => (clone $posted)->max('created_at'),
        ];
    }

    /**
     * Money of theirs sitting in escrow right now — funded, not yet released. Summed per engagement
     * through the ledger rather than from milestone rows, so the figure agrees with the platform's
     * escrow liability on the dashboard instead of being a second opinion about it.
     */
    private function escrowHeld(string $partyId): int
    {
        $ledger = app(Ledger::class);

        return Engagement::query()
            ->whereIn('job_id', Job::query()->where('customer_party_id', $partyId)->select('id'))
            ->whereNull('completed_at')
            ->pluck('id')
            ->sum(fn (string $id): int => $ledger->escrowHeldMinor($id));
    }

    /**
     * The average star rating this customer HANDS OUT, withheld below the floor.
     *
     * Not a judgement of them — it is context for the providers they rated. One customer who marks
     * every job two stars can visibly move a small provider's average (P6-09), and staff looking
     * into "why did my rating drop" need to be able to see that.
     */
    private function ratingGiven(string $partyId, int $floor): ?float
    {
        $reviews = Review::query()
            ->where('author_party_id', $partyId)
            ->whereNotNull('submitted_at')
            ->pluck('rating');

        return $reviews->count() >= $floor ? round((float) $reviews->avg(), 2) : null;
    }
}
