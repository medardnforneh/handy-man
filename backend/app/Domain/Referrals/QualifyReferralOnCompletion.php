<?php

declare(strict_types=1);

namespace App\Domain\Referrals;

use App\Events\OutboxMessagePublished;
use App\Models\Engagement;

/**
 * Qualifies a referral when the referee completes a job (build plan P8-01). Rides the outbox seam:
 * `engagement.completed` for the referee (the job's customer) qualifies their pending referral and
 * books the reward. Idempotent — a referee's second completion qualifies nothing new.
 */
final class QualifyReferralOnCompletion
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function handle(OutboxMessagePublished $event): void
    {
        if ($event->type !== 'engagement.completed') {
            return;
        }

        $engagementId = $event->payload['engagement_id'] ?? null;
        if (! is_string($engagementId)) {
            return;
        }

        $engagement = Engagement::query()->with('job')->find($engagementId);
        if ($engagement === null) {
            return;
        }

        $this->referrals->qualify($engagement->job->customer_party_id);
    }
}
