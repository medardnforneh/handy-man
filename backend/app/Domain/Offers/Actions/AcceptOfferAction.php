<?php

declare(strict_types=1);

namespace App\Domain\Offers\Actions;

use App\Domain\Access\Capabilities\AcceptPaidJob;
use App\Domain\Engagements\LeadAssigner;
use App\Domain\Jobs\JobStateMachine;
use App\Domain\Jobs\JobStatus;
use App\Domain\Offers\OfferNotAcceptable;
use App\Domain\Offers\OfferStatus;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * The provider accepts a customer's direct offer (build plan P2-06). This is the concurrency-critical
 * path: it locks the job row (`lockForUpdate`) so N simultaneous accepts serialize, the winner
 * engages the job and the losers see the job is no longer `offered`. The engagement's `job_id UNIQUE`
 * + the accepted-offer partial unique index are the DB backstops — 20 parallel accepts → exactly one
 * engagement.
 *
 * Before any of that, the accept-paid-job capability gates on `identity_verified` keyed to the job's
 * engagement_mode + skill risk tier (P2-06b, doc 10): an on-site job needs full ID; a remote job the
 * lighter (phone-confirmed) check.
 *
 * When the provider is an individual, one `lead` assignment is auto-created (doc 02 uniform-assignment
 * rule); a company's dispatcher creates assignments instead (P2-08).
 */
final class AcceptOfferAction
{
    public function __construct(
        private readonly JobStateMachine $stateMachine,
        private readonly AcceptPaidJob $acceptPaidJob,
        private readonly LeadAssigner $leadAssigner,
        private readonly Outbox $outbox,
    ) {}

    public function handle(User $provider, JobOffer $offer): Engagement
    {
        $job = $offer->job()->with('skill')->firstOrFail();

        // P2-06b — fact gate BEFORE the transaction (a precondition_unmet is not a race).
        $this->acceptPaidJob->authorize($provider, [
            'engagement_mode' => $job->engagement_mode->value,
            'risk_tier' => $job->skill->risk_tier,
        ]);

        return DB::transaction(function () use ($provider, $offer): Engagement {
            // Serialize concurrent accepts on this job.
            $job = Job::query()->whereKey($offer->job_id)->lockForUpdate()->firstOrFail();
            $offer = JobOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();

            // The provider must own this offer, and the offer/job must still be acceptable.
            if ($offer->provider_party_id !== $provider->party_id
                || ! $offer->isPending()
                || $job->status !== JobStatus::Offered) {
                throw new OfferNotAcceptable;
            }

            $offer->forceFill(['status' => OfferStatus::Accepted->value, 'responded_at' => now()])->save();

            // Every other pending offer on this job is now moot.
            JobOffer::query()
                ->where('job_id', $job->id)
                ->whereKeyNot($offer->id)
                ->where('status', OfferStatus::Pending->value)
                ->update(['status' => OfferStatus::Superseded->value]);

            $this->stateMachine->transition($job, JobStatus::Engaged);

            $engagement = Engagement::query()->create([
                'job_id' => $job->id,
                'provider_party_id' => $offer->provider_party_id,
                'offer_id' => $offer->id,
                'agreed_amount_minor' => $offer->amount_minor ?? $job->budget_minor ?? 0,
                'currency' => 'XAF',
                'accepted_at' => now(),
            ]);

            $this->leadAssigner->assignIfIndividual($engagement, $offer->provider_party_id);

            $this->outbox->publish('engagement.created', [
                'engagement_id' => $engagement->id,
                'job_id' => $job->id,
                'provider_party_id' => $offer->provider_party_id,
            ]);

            return $engagement;
        });
    }
}
