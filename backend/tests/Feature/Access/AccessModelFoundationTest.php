<?php

declare(strict_types=1);

use App\Domain\Access\Capabilities\AcceptPaidJob;
use App\Domain\Access\Facts\Fact;
use App\Domain\Access\Facts\FactDeriver;
use App\Domain\Access\Facts\FactResult;
use App\Domain\Access\PreconditionUnmetException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * P0-17 acceptance (doc 10): a guarded action with an unmet fact returns a structured
 * `precondition_unmet` (missing_fact + resolve deep link), NOT a 403; the accept-paid-job gate
 * keys the required tier on engagement_mode; facts derive-and-cache with explicit invalidation.
 */

/** Register a controllable identity tier for the test's users. */
function stubIdentityTier(int $tier): void
{
    app(FactDeriver::class)->register(
        Fact::IdentityVerified,
        fn (User $user, array $context): FactResult => FactResult::tier($tier),
    );
}

it('returns precondition_unmet (not 403) when identity is unverified', function () {
    stubIdentityTier(0); // brand-new user, nothing proven

    Route::middleware('api')->post('/api/v1/_test/accept-onsite', function () {
        app(AcceptPaidJob::class)->authorize(
            request()->user(),
            ['engagement_mode' => 'onsite', 'risk_tier' => 1],
        );

        return response()->json(['ok' => true]);
    });

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(
        '/api/v1/_test/accept-onsite',
        [],
        ['Idempotency-Key' => (string) Str::uuid()],
    );

    $response->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('error', 'precondition_unmet')
        ->assertJsonPath('capability', 'accept_paid_job')
        ->assertJsonPath('missing_fact', 'identity_verified')
        ->assertJsonPath('required_tier', 2)
        ->assertJsonPath('resolve.type', 'verification')
        ->assertJsonPath('resolve.deep_link', '/provider/verify');

    // Explicitly NOT a 403.
    expect($response->status())->not->toBe(403);
});

it('lets a phone-confirmed (tier 1) user accept a REMOTE job under the lighter check', function () {
    stubIdentityTier(1);

    $user = User::factory()->create();

    // remote requires tier 1 → satisfied.
    expect(fn () => app(AcceptPaidJob::class)->authorize($user, ['engagement_mode' => 'remote']))
        ->not->toThrow(PreconditionUnmetException::class);

    // but the SAME user cannot accept an on-site job (needs tier 2).
    expect(fn () => app(AcceptPaidJob::class)->authorize($user, ['engagement_mode' => 'onsite']))
        ->toThrow(PreconditionUnmetException::class);
});

it('requires tier 3 for a high-risk on-site skill', function () {
    $gate = app(AcceptPaidJob::class);

    expect($gate->requiredTier('remote'))->toBe(1)
        ->and($gate->requiredTier('onsite', riskTier: 1))->toBe(2)
        ->and($gate->requiredTier('hybrid', riskTier: 2))->toBe(2)
        ->and($gate->requiredTier('onsite', riskTier: 3))->toBe(3);
});

it('derives an unregistered fact as unmet (safe default)', function () {
    // Fresh deriver with no resolvers registered.
    $facts = new FactDeriver(ttlSeconds: 60);
    $user = User::factory()->create();

    expect($facts->derive($user, Fact::HasPayoutMethod)->satisfied)->toBeFalse();
});

it('caches a derived fact and recomputes only after forget()', function () {
    $facts = new FactDeriver(ttlSeconds: 300);
    $user = User::factory()->create();

    $calls = 0;
    $tier = 0;
    $facts->register(Fact::IdentityVerified, function () use (&$calls, &$tier): FactResult {
        $calls++;

        return FactResult::tier($tier);
    });

    expect($facts->derive($user, Fact::IdentityVerified)->level)->toBe(0);
    // Underlying value changes, but the cache still serves the old result.
    $tier = 2;
    expect($facts->derive($user, Fact::IdentityVerified)->level)->toBe(0)
        ->and($calls)->toBe(1);

    // After invalidation it recomputes.
    $facts->forget($user, Fact::IdentityVerified);
    expect($facts->derive($user, Fact::IdentityVerified)->level)->toBe(2)
        ->and($calls)->toBe(2);
});

/**
 * A cache HIT must return a usable FactResult on a store that actually serializes.
 *
 * The suite runs on the `array` store, which hands objects straight back without serializing —
 * so caching the FactResult object passed every test here while returning
 * `__PHP_Incomplete_Class` in production, where `cache.serializable_classes` is false and the
 * store is Redis. The second capability check of any flow 500'd with a TypeError. This test
 * drives the `file` store so a serialize/unserialize round trip really happens.
 */
it('returns a real FactResult from a cache hit on a serializing store', function () {
    config()->set('cache.serializable_classes', false);
    Cache::store('file')->flush();

    // Bind the file store as the default for this test — Cache::remember() inside the deriver
    // resolves the default store, which is what production does.
    config()->set('cache.default', 'file');

    $facts = new FactDeriver(ttlSeconds: 300);
    $user = User::factory()->create();
    $facts->register(Fact::IdentityVerified, fn (): FactResult => FactResult::tier(2));

    $miss = $facts->derive($user, Fact::IdentityVerified);   // computed
    $hit = $facts->derive($user, Fact::IdentityVerified);    // read back out of the cache

    expect($miss)->toBeInstanceOf(FactResult::class)
        ->and($hit)->toBeInstanceOf(FactResult::class)
        ->and($hit->satisfied)->toBeTrue()
        ->and($hit->level)->toBe(2)
        ->and($hit->meets(2))->toBeTrue();

    Cache::store('file')->flush();
});
