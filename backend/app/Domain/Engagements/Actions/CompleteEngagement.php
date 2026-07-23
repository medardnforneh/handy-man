<?php

declare(strict_types=1);

namespace App\Domain\Engagements\Actions;

use App\Models\Engagement;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Marks an engagement complete (build plan P7-02). Sets `completed_at` and announces
 * `engagement.completed` on the outbox — the event the follow-up orchestrator turns into a
 * review_request (+2h) and review_reminder (+3d). Idempotent: completing an already-complete
 * engagement is a no-op that re-announces nothing.
 */
final class CompleteEngagement
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(Engagement $engagement): Engagement
    {
        if ($engagement->completed_at !== null) {
            return $engagement;
        }

        return DB::transaction(function () use ($engagement): Engagement {
            $engagement->update(['completed_at' => now()]);

            $this->outbox->publish('engagement.completed', [
                'engagement_id' => $engagement->id,
                'job_id' => $engagement->job_id,
            ]);

            return $engagement;
        });
    }
}
