<?php

declare(strict_types=1);

use App\Domain\Reviews\Actions\RevealDueReviews;
use App\Domain\Reviews\RatingCalculator;
use App\Models\Engagement;
use App\Models\Job;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P6-08 / P6-09 acceptance (doc 02/04): double-blind reviews — neither side is visible until both
 * submit or the 14-day window closes, then both reveal at once — and a Bayesian shrinkage estimator
 * for display, so a single 5★ can't outrank a veteran.
 */

/**
 * @return array{customer: User, provider: User, providerParty: string, engagement: Engagement}
 */
function reviewEngagement(): array
{
    $customer = User::factory()->create();
    $job = Job::factory()->create([
        'customer_party_id' => $customer->party_id,
        'created_by_user_id' => $customer->id,
    ]);
    $provider = User::factory()->create();
    ProviderProfile::factory()->create(['party_id' => $provider->party_id]);
    $engagement = Engagement::factory()->create([
        'job_id' => $job->id,
        'provider_party_id' => $provider->party_id,
    ]);

    return ['customer' => $customer, 'provider' => $provider, 'providerParty' => $provider->party_id, 'engagement' => $engagement];
}

function submitReview(User $author, string $engagementId, int $rating)
{
    Sanctum::actingAs($author);

    return test()->postJson("/api/v1/engagements/{$engagementId}/reviews", ['rating' => $rating], ['Idempotency-Key' => (string) Str::uuid()]);
}

it('keeps a review hidden until both sides submit, then reveals both (P6-08)', function () {
    ['customer' => $customer, 'provider' => $provider, 'providerParty' => $providerParty, 'engagement' => $engagement] = reviewEngagement();

    // Customer reviews the provider — pending, invisible to the world.
    submitReview($customer, $engagement->id, 5)
        ->assertCreated()
        ->assertJsonPath('data.visibility', 'pending')
        ->assertJsonPath('data.rating', null); // content withheld even in the echo

    $this->getJson("/api/v1/providers/{$providerParty}/reviews")->assertOk()->assertJsonCount(0, 'data');

    // Provider reviews the customer — now BOTH publish at once.
    submitReview($provider, $engagement->id, 4)->assertCreated();

    $this->getJson("/api/v1/providers/{$providerParty}/reviews")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.rating', 5); // the customer's review of the provider is now visible

    expect(Review::query()->where('visibility', 'published')->count())->toBe(2);
});

it('publishes a lone review once the window closes (P6-08)', function () {
    ['customer' => $customer, 'providerParty' => $providerParty, 'engagement' => $engagement] = reviewEngagement();

    submitReview($customer, $engagement->id, 5);
    $this->getJson("/api/v1/providers/{$providerParty}/reviews")->assertJsonCount(0, 'data'); // still hidden

    $this->travel(15)->days();
    expect(app(RevealDueReviews::class)->handle())->toBe(1);

    $this->getJson("/api/v1/providers/{$providerParty}/reviews")->assertOk()->assertJsonCount(1, 'data');
});

it('updates the shrunk display rating on publish (P6-09)', function () {
    ['customer' => $customer, 'provider' => $provider, 'providerParty' => $providerParty, 'engagement' => $engagement] = reviewEngagement();

    submitReview($customer, $engagement->id, 5);
    submitReview($provider, $engagement->id, 3); // reveals both

    // One 5★ shrinks toward the prior (4.0, weight 10): (10*4 + 5) / 11 = 4.09 — not a bare 5.0.
    $avg = (float) ProviderProfile::query()->where('party_id', $providerParty)->value('rating_avg');
    expect($avg)->toBe(4.09);
    expect((int) ProviderProfile::query()->where('party_id', $providerParty)->value('rating_count'))->toBe(1);
});

it('does not let a single 5-star outrank a veteran (P6-09 shrinkage)', function () {
    $calc = app(RatingCalculator::class);

    $newbie = $calc->shrunk(1, 5);          // one 5★
    $veteran = $calc->shrunk(200, (int) (200 * 4.8)); // 200 reviews averaging 4.8 (sum 960)

    expect($newbie)->toBeLessThan($veteran)
        ->and($newbie)->toBe(4.09)
        ->and($calc->shrunk(0, 0))->toBeNull(); // unrated shows nothing, not the bare prior
});

it('rejects a second review from the same author (409)', function () {
    ['customer' => $customer, 'engagement' => $engagement] = reviewEngagement();

    submitReview($customer, $engagement->id, 5)->assertCreated();
    submitReview($customer, $engagement->id, 1)->assertStatus(409);
});

it('forbids a non-party from reviewing (403)', function () {
    ['engagement' => $engagement] = reviewEngagement();

    submitReview(User::factory()->create(), $engagement->id, 5)->assertStatus(403);
});
