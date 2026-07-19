<?php

declare(strict_types=1);

namespace App\Domain\Engagements\Actions;

use App\Domain\Engagements\AssignmentStatus;
use App\Models\Assignment;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Remove a worker from an engagement (build plan P2-08). A soft removal: the row stays for the audit
 * trail with status `removed`, which the `one_lead_per_engagement` partial index excludes — so
 * removing the lead frees the slot for a new one. Authorization is the EngagementPolicy (controller).
 */
final class UnassignWorker
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(Assignment $assignment): void
    {
        if ($assignment->status === AssignmentStatus::Removed) {
            return; // idempotent
        }

        DB::transaction(function () use ($assignment): void {
            $assignment->forceFill([
                'status' => AssignmentStatus::Removed->value,
                'removed_at' => now(),
            ])->save();

            $this->outbox->publish('assignment.removed', [
                'assignment_id' => $assignment->id,
                'engagement_id' => $assignment->engagement_id,
                'worker_user_id' => $assignment->worker_user_id,
            ]);
        });
    }
}
