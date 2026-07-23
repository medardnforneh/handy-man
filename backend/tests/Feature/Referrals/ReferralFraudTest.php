<?php

declare(strict_types=1);

use App\Domain\Engagements\Actions\CompleteEngagement;
use App\Domain\Referrals\ReferralService;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\Referral;
use App\Models\User;
use App\Support\OutboxRelay;

/**
 * P8-02 acceptance (doc 04): referral fraud controls. A referrer over the weekly velocity limit has
 * new referrals flagged for a human review queue — not auto-qualified — and an admin can clear them.
 */
function completeFirstJobFor(User $referee): void
{
    $job = Job::factory()->create(['customer_party_id' => $referee->party_id, 'created_by_user_id' => $referee->id]);
    $engagement = Engagement::factory()->create(['job_id' => $job->id]);
    app(CompleteEngagement::class)->handle($engagement);
    app(OutboxRelay::class)->drain();
}

it('flags referrals beyond the weekly velocity limit for review', function () {
    config(['referrals.weekly_velocity_limit' => 3]);
    $referrer = User::factory()->create();
    $code = app(ReferralService::class)->codeFor($referrer->party_id);

    $flags = [];
    for ($i = 0; $i < 4; $i++) {
        $referee = User::factory()->create();
        $flags[] = app(ReferralService::class)->claim($referee->party_id, $code)->flagged_for_review;
    }

    // The first 3 are clean; the 4th (over the limit) is flagged.
    expect($flags)->toBe([false, false, false, true]);
});

it('does not auto-qualify a flagged referral until an admin clears it', function () {
    config(['referrals.weekly_velocity_limit' => 0]); // everything is flagged
    $referrer = User::factory()->create();
    $referee = User::factory()->create();
    $code = app(ReferralService::class)->codeFor($referrer->party_id);
    $referral = app(ReferralService::class)->claim($referee->party_id, $code);
    expect($referral->flagged_for_review)->toBeTrue();

    completeFirstJobFor($referee);
    expect($referral->fresh()->status)->toBe('pending'); // held, not qualified

    app(ReferralService::class)->clearReview($referral->fresh());
    expect($referral->fresh()->status)->toBe('qualified'); // cleared → qualifies immediately
});
