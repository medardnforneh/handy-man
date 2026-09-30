<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * The API is rate limited.
 *
 * It was not, anywhere, and nothing here noticed: Laravel 11 moved `throttle:api` out of the
 * default API middleware group AND stopped defining the `api` limiter, and `bootstrap/app.php`
 * never put either back. So `/auth/otp/verify`, `/auth/refresh`, the public directory and the
 * public webhook — which writes a row per unsigned request — all answered as fast as they were
 * asked. These tests exist to make the absence of a limit fail out loud next time.
 *
 * The buckets live in the cache, which is the `array` store here and therefore fresh per test.
 */
it('throttles the credential endpoints per IP, with a machine-readable problem+json', function () {
    config(['api.rate_limits.auth' => 3]);

    foreach (range(1, 3) as $_) {
        // Wrong codes: what matters is that the request was SERVED, not that it succeeded.
        $this->postJson('/api/v1/auth/otp/verify',
            ['phone_e164' => '+237699111222', 'code' => '000000', 'purpose' => 'login'],
            ['Idempotency-Key' => (string) Str::uuid()],
        )->assertStatus(422);
    }

    $limited = $this->postJson('/api/v1/auth/otp/verify',
        ['phone_e164' => '+237699111222', 'code' => '000000', 'purpose' => 'login'],
        ['Idempotency-Key' => (string) Str::uuid()],
    );

    $limited->assertStatus(429)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Too many requests');

    // A client that cannot tell "slow down, for this long" from "something broke" retries
    // immediately and makes it worse — and the offline write queue is exactly such a client.
    expect($limited->json('type'))->toEndWith('/rate-limited')
        ->and($limited->headers->get('Retry-After'))->not->toBeNull()
        ->and($limited->json('retry_after_seconds'))->toBeGreaterThan(0);
});

it('throttles the public payment webhook, which is unauthenticated and writes', function () {
    config(['api.rate_limits.webhooks' => 2]);

    foreach (range(1, 2) as $_) {
        $this->postJson('/api/v1/webhooks/payments/fake', ['reference' => 'x'])->assertNoContent(401);
    }

    $this->postJson('/api/v1/webhooks/payments/fake', ['reference' => 'x'])->assertStatus(429);

    // Two attempts recorded, not three: the limiter refuses before the audit row is written.
    $this->assertDatabaseCount('payment_events', 2);
});

it('throttles an authenticated caller by user, not by shared IP', function () {
    // Most of this product's users share a carrier NAT. An IP-keyed limit on the authenticated
    // API would throttle a city, so the `api` limiter keys on the sanctum user where there is one.
    config(['api.rate_limits.api' => 2]);

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Sanctum::actingAs($alice);
    $this->getJson('/api/v1/follow-ups')->assertOk();
    $this->getJson('/api/v1/follow-ups')->assertOk();
    $this->getJson('/api/v1/follow-ups')->assertStatus(429); // Alice's bucket is spent

    // Bob, same IP, has his own.
    Sanctum::actingAs($bob);
    $this->getJson('/api/v1/follow-ups')->assertOk();
});
