<?php

declare(strict_types=1);

namespace App\Domain\Reviews;

/**
 * The shrinkage estimator for displayed ratings (build plan P6-09, doc 04). A raw mean lets a brand
 * new pro with a single 5★ outrank a veteran at 4.8 over 200 jobs — so the displayed number is a
 * Bayesian average pulled toward the prior mean with a prior weight of ~10 pseudo-reviews:
 *
 *   shrunk = (priorWeight · priorMean + Σ ratings) / (priorWeight + count)
 *
 * With prior (4.0, 10): one 5★ → 4.09, but 200×4.8 → 4.76. The veteran wins, as it should.
 */
final class RatingCalculator
{
    public function priorMean(): float
    {
        return (float) config('reviews.prior_mean', 4.0);
    }

    public function priorWeight(): int
    {
        return (int) config('reviews.prior_weight', 10);
    }

    /**
     * The shrunk display rating, rounded to 2 dp. Null when there are no reviews at all — an unrated
     * provider shows "no rating yet", not the bare prior.
     */
    public function shrunk(int $count, int $ratingSum): ?float
    {
        if ($count <= 0) {
            return null;
        }

        $weight = $this->priorWeight();
        $value = ($weight * $this->priorMean() + $ratingSum) / ($weight + $count);

        return round($value, 2);
    }
}
