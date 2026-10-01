<?php

declare(strict_types=1);

use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P0-06 acceptance: a replayed Idempotency-Key returns the stored response and does NOT
 * re-execute the handler. Plus the surrounding protocol: required header, in-flight/reuse
 * conflicts, and per-key isolation.
 */
beforeEach(function () {
    // A side-effecting POST route: each real execution increments a counter we can assert on.
    Route::middleware('api')->post('/api/v1/_test/increment', function () {
        $n = Cache::increment('idem_test_counter');

        return response()->json(['count' => $n], 201);
    });

    Cache::forget('idem_test_counter');
});

it('executes the first request and replays the second without re-executing', function () {
    $key = ['Idempotency-Key' => (string) Str::uuid()];

    $first = $this->postJson('/api/v1/_test/increment', [], $key);
    $first->assertStatus(201)->assertJsonPath('count', 1);
    expect($first->headers->get('Idempotency-Replayed'))->toBeNull();

    $second = $this->postJson('/api/v1/_test/increment', [], $key);
    $second->assertStatus(201)->assertJsonPath('count', 1); // same stored body, NOT 2
    expect($second->headers->get('Idempotency-Replayed'))->toBe('true');

    // The handler ran exactly once.
    expect(Cache::get('idem_test_counter'))->toBe(1);

    $this->assertDatabaseCount('idempotency_keys', 1);
});

it('requires an Idempotency-Key on mutating requests', function () {
    $this->postJson('/api/v1/_test/increment', [])
        ->assertStatus(400)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('title', 'Idempotency-Key required');
});

it('treats distinct keys as distinct operations', function () {
    $this->postJson('/api/v1/_test/increment', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertJsonPath('count', 1);
    $this->postJson('/api/v1/_test/increment', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertJsonPath('count', 2);

    expect(Cache::get('idem_test_counter'))->toBe(2);
});

it('rejects reusing a key for a different request payload', function () {
    $key = ['Idempotency-Key' => 'fixed-key-123'];

    $this->postJson('/api/v1/_test/increment', ['a' => 1], $key)->assertStatus(201);

    $this->postJson('/api/v1/_test/increment', ['a' => 2], $key)
        ->assertStatus(422)
        ->assertJsonPath('title', 'Idempotency-Key reused for a different request');
});

it('does not require a key on non-mutating requests', function () {
    $this->getJson('/api/v1/meta')->assertOk();
});

it('scopes a key to the caller: one user cannot replay another user’s stored response', function () {
    $key = ['Idempotency-Key' => 'shared-key-abc'];
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Sanctum::actingAs($alice);
    $this->postJson('/api/v1/_test/increment', [], $key)
        ->assertStatus(201)
        ->assertJsonPath('count', 1);

    // Bob presents the SAME key, method, path and body. Before the claim was scoped to the
    // caller, this replayed Alice's stored response verbatim — her body, her ids, count 1.
    Sanctum::actingAs($bob);
    $second = $this->postJson('/api/v1/_test/increment', [], $key);

    $second->assertStatus(201)->assertJsonPath('count', 2); // Bob's own execution
    expect($second->headers->get('Idempotency-Replayed'))->toBeNull()
        ->and(Cache::get('idem_test_counter'))->toBe(2);

    // Two claims, one per caller, and each names its owner.
    $this->assertDatabaseCount('idempotency_keys', 2);
    expect(IdempotencyKey::where('idempotency_key', 'shared-key-abc')->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$alice->getKey(), $bob->getKey()])->sort()->values()->all());
});

it('records the authenticated user on the claim', function () {
    // The user was read off the DEFAULT (`web`, session) guard, which a Bearer client never
    // satisfies — so every row this table ever held recorded user_id as null.
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/_test/increment', [], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertStatus(201);

    expect(IdempotencyKey::sole()->user_id)->toBe($user->getKey());
});

it('still claims atomically for an unauthenticated caller', function () {
    // The /auth/* routes carry a key and have no user. `NULLS NOT DISTINCT` on the index is what
    // keeps the claim atomic for them: without it every guest INSERT succeeds and the replay
    // protection those routes depend on quietly disappears.
    $key = ['Idempotency-Key' => 'guest-key-xyz'];

    $this->postJson('/api/v1/_test/increment', [], $key)->assertStatus(201)->assertJsonPath('count', 1);
    $replay = $this->postJson('/api/v1/_test/increment', [], $key);

    $replay->assertStatus(201)->assertJsonPath('count', 1);
    expect($replay->headers->get('Idempotency-Replayed'))->toBe('true')
        ->and(Cache::get('idem_test_counter'))->toBe(1);

    $this->assertDatabaseCount('idempotency_keys', 1);
});
