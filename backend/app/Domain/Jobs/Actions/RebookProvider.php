<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\RebookRefused;
use App\Domain\Offers\Actions\CreateDirectOffer;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rebook a known provider in one tap (build plan P8-05). Clones the customer's most recent job with
 * that provider into a fresh open job and sends them a direct offer — the whole "book them again"
 * flow behind a single action. Refused if there's no prior engagement (nothing to clone), and the
 * direct-offer path still honours blocks.
 */
final class RebookProvider
{
    public function __construct(
        private readonly CreateJob $createJob,
        private readonly PublishJob $publishJob,
        private readonly CreateDirectOffer $offer,
    ) {}

    public function handle(User $customer, string $providerPartyId): JobOffer
    {
        $lastJob = Job::query()
            ->where('customer_party_id', $customer->party_id)
            ->whereIn('id', Engagement::query()->where('provider_party_id', $providerPartyId)->select('job_id'))
            ->latest('created_at')
            ->first();

        if ($lastJob === null) {
            throw new RebookRefused;
        }

        return DB::transaction(function () use ($customer, $providerPartyId, $lastJob): JobOffer {
            $job = $this->createJob->handle($customer, [
                'skill_id' => $lastJob->skill_id,
                'address_id' => $lastJob->address_id,
                'title' => $lastJob->title,
                'description' => $lastJob->description,
                'engagement_mode' => $lastJob->engagement_mode->value,
                'price_model' => $lastJob->price_model,
            ]);
            $this->publishJob->handle($job);

            return $this->offer->handle($job, $providerPartyId, null, 'Rebooking a previous provider');
        });
    }
}
