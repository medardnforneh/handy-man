<?php

declare(strict_types=1);

use App\Domain\Jobs\JobStatus;
use App\Domain\Metrics\MarketplaceAnalytics;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\JobOffer;

/**
 * P8-06 acceptance (doc 05): marketplace analytics — liquidity (offered rate), match rate,
 * time-to-offer, leakage. Computed from the models over a rolling window.
 */
it('computes liquidity and match rate from jobs, offers and engagements', function () {
    // Two jobs. One drew an offer and converted; one drew nothing.
    $converted = Job::factory()->status(JobStatus::Engaged)->create(['published_at' => now()->subMinutes(10)]);
    $offer = JobOffer::factory()->create(['job_id' => $converted->id, 'created_at' => now()->subMinutes(5)]);
    Engagement::factory()->create(['job_id' => $converted->id, 'offer_id' => $offer->id]);

    Job::factory()->status(JobStatus::Open)->create(); // no offer, no engagement

    $summary = app(MarketplaceAnalytics::class)->summary();

    expect($summary['jobs'])->toBe(2)
        ->and($summary['offered_rate'])->toBe(0.5)   // 1 of 2 jobs drew an offer
        ->and($summary['match_rate'])->toBe(0.5)     // 1 of 2 converted
        ->and($summary['avg_time_to_offer_seconds'])->toBeGreaterThan(0);
});

it('returns zeroed rates with no jobs', function () {
    $summary = app(MarketplaceAnalytics::class)->summary();

    expect($summary['jobs'])->toBe(0)
        ->and($summary['offered_rate'])->toBe(0.0)
        ->and($summary['match_rate'])->toBe(0.0)
        ->and($summary['avg_time_to_offer_seconds'])->toBeNull();
});
