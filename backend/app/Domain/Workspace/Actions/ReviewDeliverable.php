<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Actions;

use App\Domain\Jobs\JobProgress;
use App\Domain\Workspace\DeliverableStatus;
use App\Models\Deliverable;
use App\Models\Job;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The customer accepts or rejects a submitted deliverable (build plan P4-08). Only a submitted
 * deliverable can be reviewed; a rejection carries a reason.
 */
final class ReviewDeliverable
{
    public function __construct(
        private readonly Outbox $outbox,
        private readonly JobProgress $progress,
    ) {}

    public function handle(Deliverable $deliverable, bool $accept, ?string $rejectReason = null): Deliverable
    {
        return DB::transaction(function () use ($deliverable, $accept, $rejectReason): Deliverable {
            $locked = Deliverable::query()->whereKey($deliverable->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== DeliverableStatus::Submitted) {
                throw new InvalidArgumentException('Only a submitted deliverable can be reviewed.');
            }

            $locked->update([
                'status' => $accept ? DeliverableStatus::Accepted->value : DeliverableStatus::Rejected->value,
                'reviewed_at' => now(),
                'reject_reason' => $accept ? null : $rejectReason,
            ]);

            // Rejected work is work in progress again. Acceptance moves nothing here: the customer's
            // explicit completion (CompleteEngagement) is what ends the job.
            if (! $accept) {
                $job = Job::query()->whereHas('engagement', fn ($q) => $q->whereKey($locked->engagement_id))->lockForUpdate()->first();
                if ($job !== null) {
                    $this->progress->reopen($job);
                }
            }

            $this->outbox->publish($accept ? 'deliverable.accepted' : 'deliverable.rejected', [
                'deliverable_id' => $locked->id,
                'engagement_id' => $locked->engagement_id,
            ]);

            return $locked;
        });
    }
}
