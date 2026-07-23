<?php

declare(strict_types=1);

namespace App\Domain\Reviews\Actions;

use App\Domain\Reviews\PublishEngagementReviews;
use App\Domain\Reviews\ReviewVisibility;
use App\Models\Review;

/**
 * Reveals reviews whose window has closed (build plan P6-08). The other half of the reveal: when a
 * counterparty never submits, the lone review still publishes once the 14-day window expires — so a
 * silent no-show can't bury an honest review forever. Scheduled; idempotent.
 */
final class RevealDueReviews
{
    public function __construct(private readonly PublishEngagementReviews $publisher) {}

    /**
     * Publishes all due engagements' pending reviews. Returns the number of engagements revealed.
     */
    public function handle(): int
    {
        /** @var array<int, string> $engagementIds */
        $engagementIds = Review::query()
            ->where('visibility', ReviewVisibility::Pending->value)
            ->where('window_closes_at', '<=', now())
            ->pluck('engagement_id')
            ->unique()
            ->values()
            ->all();

        foreach ($engagementIds as $engagementId) {
            $this->publisher->handle($engagementId);
        }

        return count($engagementIds);
    }
}
