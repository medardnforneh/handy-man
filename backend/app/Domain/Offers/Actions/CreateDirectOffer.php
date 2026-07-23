<?php

declare(strict_types=1);

namespace App\Domain\Offers\Actions;

use App\Domain\Jobs\JobStateMachine;
use App\Domain\Jobs\JobStatus;
use App\Domain\Offers\OfferOrigin;
use App\Domain\Offers\OfferStatus;
use App\Domain\Safety\PartyBlocked;
use App\Models\Block;
use App\Models\Job;
use App\Models\JobOffer;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * A customer directly offers a job to a chosen provider (build plan P2-05, origin customer_direct).
 * The first offer moves the job open → offered; a customer may send offers to several providers (the
 * unique (job, provider) index just prevents two live offers to the SAME provider).
 */
final class CreateDirectOffer
{
    public function __construct(
        private readonly JobStateMachine $stateMachine,
        private readonly Outbox $outbox,
    ) {}

    public function handle(Job $job, string $providerPartyId, ?int $amountMinor = null, ?string $message = null): JobOffer
    {
        // A block is honoured at offer creation (P6-07) — the third of the three paths, with search
        // and dispatch ranking. Without this, a block would leak through direct offers.
        if (Block::existsBetween($job->customer_party_id, $providerPartyId)) {
            throw new PartyBlocked;
        }

        return DB::transaction(function () use ($job, $providerPartyId, $amountMinor, $message): JobOffer {
            $offer = JobOffer::query()->create([
                'job_id' => $job->id,
                'provider_party_id' => $providerPartyId,
                'origin' => OfferOrigin::CustomerDirect->value,
                'status' => OfferStatus::Pending->value,
                'amount_minor' => $amountMinor,
                'currency' => 'XAF',
                'message' => $message,
                'expires_at' => now()->addHours((int) config('offers.ttl_hours')),
            ]);

            if ($job->status === JobStatus::Open) {
                $this->stateMachine->transition($job, JobStatus::Offered);
            }

            $this->outbox->publish('offer.created', ['offer_id' => $offer->id, 'job_id' => $job->id]);

            return $offer;
        });
    }
}
