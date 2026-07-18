<?php

declare(strict_types=1);

use App\Domain\Access\Capabilities\AcceptPaidJob;
use App\Domain\Access\Facts\Fact;
use App\Domain\Access\Facts\FactDeriver;
use App\Domain\Access\Facts\FactResult;
use App\Domain\Access\PreconditionUnmetException;
use App\Models\User;
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
