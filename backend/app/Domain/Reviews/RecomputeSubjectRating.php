<?php

declare(strict_types=1);

namespace App\Domain\Reviews;

use App\Models\ProviderProfile;
use App\Models\Review;

/**
 * Recomputes a party's cached display rating from its published reviews (build plan P6-09). The cache
 * (`provider_profiles.rating_avg`/`rating_count`) is derived, never authoritative — recomputed on
 * every publish. `rating_avg` is the shrunk (Bayesian) value; `rating_count` is the RAW count, so a
 * sample-size floor (P6-12) can decide whether to display it at all.
 */
final class RecomputeSubjectRating
{
    public function __construct(private readonly RatingCalculator $calculator) {}

    public function handle(string $subjectPartyId): void
    {
        /** @var object{c: int, s: int} $agg */
        $agg = Review::query()
            ->where('subject_party_id', $subjectPartyId)
            ->where('visibility', ReviewVisibility::Published->value)
            ->selectRaw('count(*) as c, coalesce(sum(rating), 0) as s')
            ->first();

        $count = (int) $agg->c;
        $sum = (int) $agg->s;

        ProviderProfile::query()
            ->where('party_id', $subjectPartyId)
            ->update([
                'rating_avg' => $this->calculator->shrunk($count, $sum),
                'rating_count' => $count,
            ]);
    }
}
