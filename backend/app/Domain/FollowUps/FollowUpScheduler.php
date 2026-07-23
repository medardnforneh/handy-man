<?php

declare(strict_types=1);

namespace App\Domain\FollowUps;

use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Schedules and cancels follow-ups (doc 07, rule 1). Scheduling is **idempotent on `dedupe_key`** —
 * replaying an at-least-once outbox event any number of times yields exactly one follow-up. The
 * dedupe key is `{kind}:{anchor_type}:{anchor_id}:{sequence}`, so a review_request and its later
 * review_reminder on the same engagement are distinct, but two of the same are not.
 */
final class FollowUpScheduler
{
    public function schedule(
        FollowUpKind $kind,
        User $target,
        FollowUpChannel $channel,
        Carbon $scheduledFor,
        string $anchorType,
        string $anchorId,
        int $sequence = 1,
        ?string $jobId = null,
        ?string $engagementId = null,
        ?string $quotationId = null,
        ?string $warrantyId = null,
        ?string $createdByUserId = null,
    ): FollowUp {
        $dedupeKey = "{$kind->value}:{$anchorType}:{$anchorId}:{$sequence}";

        return FollowUp::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'kind' => $kind->value,
                'target_user_id' => $target->id,
                'target_party_id' => $target->party_id,
                'channel' => $channel->value,
                'scheduled_for' => $scheduledFor,
                'status' => FollowUpStatus::Scheduled->value,
                'job_id' => $jobId,
                'engagement_id' => $engagementId,
                'quotation_id' => $quotationId,
                'warranty_id' => $warrantyId,
                'created_by_user_id' => $createdByUserId,
            ],
        );
    }

    /**
     * Cancel every still-scheduled follow-up whose dedupe key starts with $prefix — the event-driven
     * cancellation (rule 1). Returns how many were cancelled.
     */
    public function cancelByPrefix(string $prefix, string $reason): int
    {
        return FollowUp::query()
            ->where('dedupe_key', 'like', $prefix.'%')
            ->where('status', FollowUpStatus::Scheduled->value)
            ->update([
                'status' => FollowUpStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
    }
}
