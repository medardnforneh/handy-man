<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\DeliverableStatus;
use App\Models\Deliverable;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The customer accepts or rejects a submitted deliverable (build plan P4-08). Only a submitted
 * deliverable can be reviewed; a rejection carries a reason.
 */
final class ReviewDeliverable
{
    public function __construct(private readonly Outbox $outbox) {}

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

            $this->outbox->publish($accept ? 'deliverable.accepted' : 'deliverable.rejected', [
                'deliverable_id' => $locked->id,
                'engagement_id' => $locked->engagement_id,
            ]);

            return $locked;
        });
    }
}
