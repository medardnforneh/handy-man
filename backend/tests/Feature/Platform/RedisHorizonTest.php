<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * P0-03 acceptance: Redis is reachable (cache round-trips through it) and the Horizon dashboard is
 * protected — deny-by-default, granted only to an explicit staff allowlist.
 */
it('round-trips a value through the Redis cache store', function () {
    $key = 'p0_03_redis_'.uniqid();

    Cache::store('redis')->put($key, 'pong', 10);

    expect(Cache::store('redis')->get($key))->toBe('pong');

    Cache::store('redis')->forget($key);
})->group('redis');

it('denies the Horizon dashboard to an unauthenticated visitor', function () {
    expect(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse();
});

it('denies the Horizon dashboard to an ordinary authenticated user', function () {
    config()->set('horizon.dashboard_emails', 'ops@handyman.cm');

    $user = User::factory()->create(['email' => 'random@example.com']);

    expect(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});

it('allows the Horizon dashboard only to a staff-allowlisted user', function () {
    config()->set('horizon.dashboard_emails', 'ops@handyman.cm, boss@handyman.cm');

    $staff = User::factory()->create(['email' => 'boss@handyman.cm']);

    expect(Gate::forUser($staff)->allows('viewHorizon'))->toBeTrue();
});
