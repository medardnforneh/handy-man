<?php

declare(strict_types=1);

namespace App\Domain\Warranties\Actions;

use App\Domain\Engagements\AssignmentRole;
use App\Domain\Engagements\AssignmentStatus;
use App\Domain\Jobs\JobStatus;
use App\Domain\Warranties\WarrantyNotClaimable;
use App\Domain\Warranties\WarrantyStatus;
use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Files a warranty claim (build plan P6-11, doc 06). The claim **spawns a real remedy job** — a fresh
 * job cloned from the original, its own engagement (origin = this claim), and a real lead assignment
 * to the original worker. Free for the customer, unpaid for the provider (agreed amount 0), but a
 * first-class tracked object with a real report, not an email thread. This closes the loop and is the
 * anti-leakage payoff: the fix only exists on-platform.
 */
final class FileWarrantyClaim
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(Warranty $warranty, string $claimedByPartyId, string $description): WarrantyClaim
    {
        if (! $warranty->isClaimable()) {
            throw new WarrantyNotClaimable;
        }

        return DB::transaction(function () use ($warranty, $claimedByPartyId, $description): WarrantyClaim {
            $original = $warranty->engagement()->with('job')->firstOrFail();
            $originalJob = $original->job;

            $claim = WarrantyClaim::query()->create([
                'warranty_id' => $warranty->id,
                'claimed_by_party_id' => $claimedByPartyId,
                'description' => $description,
                'status' => 'open',
            ]);

            $remedyJob = $this->spawnRemedyJob($originalJob);

            $remedyEngagement = Engagement::query()->create([
                'job_id' => $remedyJob->id,
                'provider_party_id' => $original->provider_party_id,
                'warranty_claim_id' => $claim->id,
                'agreed_amount_minor' => 0, // free remedy
                'currency' => $original->currency,
                'accepted_at' => now(),
            ]);

            $this->assignOriginalWorker($original, $remedyEngagement);

            $claim->update(['remedy_job_id' => $remedyJob->id]);
            $warranty->update(['status' => WarrantyStatus::Claimed->value]);

            $this->outbox->publish('warranty.claim_filed', [
                'claim_id' => $claim->id,
                'warranty_id' => $warranty->id,
                'remedy_job_id' => $remedyJob->id,
            ]);

            return $claim->refresh();
        });
    }

    private function spawnRemedyJob(Job $original): Job
    {
        return Job::query()->create([
            'reference' => $this->generateReference(),
            'customer_party_id' => $original->customer_party_id,
            'created_by_user_id' => $original->created_by_user_id,
            'skill_id' => $original->skill_id,
            'address_id' => $original->address_id,
            'title' => Str::limit('Remedy: '.$original->title, 255, ''),
            'description' => $original->description,
            'description_language' => $original->description_language,
            'engagement_mode' => $original->engagement_mode->value,
            'assignment_mode' => $original->assignment_mode,
            'price_model' => $original->price_model,
            'status' => JobStatus::Engaged->value, // pre-assigned to the original provider
            'currency' => $original->currency,
            'urgency' => $original->urgency,
        ]);
    }

    private function assignOriginalWorker(Engagement $original, Engagement $remedy): void
    {
        $lead = Assignment::query()
            ->where('engagement_id', $original->id)
            ->where('role', AssignmentRole::Lead->value)
            ->whereNull('removed_at')
            ->first();

        // Fall back to any active worker if there's no recorded lead (defensive).
        $workerUserId = $lead?->worker_user_id ?? Assignment::query()
            ->where('engagement_id', $original->id)
            ->whereNull('removed_at')
            ->value('worker_user_id');

        if ($workerUserId === null) {
            return;
        }

        Assignment::query()->create([
            'engagement_id' => $remedy->id,
            'worker_user_id' => $workerUserId,
            'assigned_by_user_id' => $workerUserId,
            'role' => AssignmentRole::Lead->value,
            'status' => AssignmentStatus::Assigned->value,
            'assigned_at' => now(),
        ]);
    }

    private function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'RMD-'.collect(range(1, 5))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (Job::query()->where('reference', $code)->exists());

        return $code;
    }
}
