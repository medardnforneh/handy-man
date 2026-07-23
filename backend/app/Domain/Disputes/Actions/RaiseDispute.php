<?php

declare(strict_types=1);

namespace App\Domain\Disputes\Actions;

use App\Models\Dispute;
use App\Models\Engagement;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * A party raises a dispute on an engagement (build plan P6-10). It queues a human adjudication (admin)
 * and alerts staff via the outbox — it never auto-moves money.
 */
final class RaiseDispute
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(Engagement $engagement, string $raisedByPartyId, string $category, string $body): Dispute
    {
        return DB::transaction(function () use ($engagement, $raisedByPartyId, $category, $body): Dispute {
            $dispute = Dispute::query()->create([
                'engagement_id' => $engagement->id,
                'raised_by_party_id' => $raisedByPartyId,
                'category' => $category,
                'body' => $body,
                'status' => 'open',
            ]);

            $this->outbox->publish('dispute.raised', [
                'dispute_id' => $dispute->id,
                'engagement_id' => $engagement->id,
                'category' => $category,
            ]);

            return $dispute;
        });
    }
}
