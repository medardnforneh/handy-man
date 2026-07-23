<?php

declare(strict_types=1);

namespace App\Domain\Reviews;

use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * The simultaneous reveal (build plan P6-08). Publishes every still-pending review on an engagement
 * at once — called when both parties have submitted, or when the window closes. Idempotent (a
 * second call finds nothing pending). Recomputes each revealed subject's cached rating.
 */
final class PublishEngagementReviews
{
    public function __construct(private readonly RecomputeSubjectRating $recompute) {}

    public function handle(string $engagementId): void
    {
        DB::transaction(function () use ($engagementId): void {
            /** @var array<int, string> $subjectIds */
            $subjectIds = Review::query()
                ->where('engagement_id', $engagementId)
                ->where('visibility', ReviewVisibility::Pending->value)
                ->pluck('subject_party_id')
                ->unique()
                ->values()
                ->all();

            if ($subjectIds === []) {
                return;
            }

            Review::query()
                ->where('engagement_id', $engagementId)
                ->where('visibility', ReviewVisibility::Pending->value)
                ->update([
                    'visibility' => ReviewVisibility::Published->value,
                    'published_at' => now(),
                ]);

            foreach ($subjectIds as $subjectPartyId) {
                $this->recompute->handle($subjectPartyId);
            }
        });
    }
}
