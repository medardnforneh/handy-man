<?php

declare(strict_types=1);

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * P1-04: a client registers its device (keyed by X-Device-Id) with a push token and app version.
 * Re-registration is an upsert, not a duplicate.
 */
function registerDevice($test, array $body, string $deviceId, ?string $appVersion = '1.4.0')
{
    return $test->postJson('/api/v1/devices', $body, array_filter([
        'X-Device-Id' => $deviceId,
        'X-App-Version' => $appVersion,
        'Idempotency-Key' => (string) Str::uuid(),
    ]));
}

it('registers a device for the authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $deviceId = (string) Str::uuid();

    registerDevice($this, ['platform' => 'android', 'push_token' => 'fcm-abc'], $deviceId)
        ->assertOk()
        ->assertJsonPath('data.id', $deviceId)
        ->assertJsonPath('data.platform', 'android')
        ->assertJsonPath('data.has_push_token', true)
        ->assertJsonPath('data.app_version', '1.4.0');

    $this->assertDatabaseHas('devices', ['id' => $deviceId, 'user_id' => $user->id, 'push_token' => 'fcm-abc']);
});

it('upserts on re-registration rather than duplicating', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $deviceId = (string) Str::uuid();

    registerDevice($this, ['platform' => 'android', 'push_token' => 'fcm-old'], $deviceId)->assertOk();
    registerDevice($this, ['platform' => 'android', 'push_token' => 'fcm-new'], $deviceId, '1.5.0')->assertOk();

    expect(Device::where('id', $deviceId)->count())->toBe(1);
    $this->assertDatabaseHas('devices', ['id' => $deviceId, 'push_token' => 'fcm-new', 'app_version' => '1.5.0']);
});

it('moves a push token to the newest device, releasing it from the old one', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $deviceA = (string) Str::uuid();
    $deviceB = (string) Str::uuid();

    registerDevice($this, ['platform' => 'android', 'push_token' => 'shared-token'], $deviceA)->assertOk();
    registerDevice($this, ['platform' => 'ios', 'push_token' => 'shared-token'], $deviceB)->assertOk();

    // The token now belongs to B; A no longer holds it (unique (user, push_token) preserved).
    $this->assertDatabaseHas('devices', ['id' => $deviceB, 'push_token' => 'shared-token']);
    $this->assertDatabaseHas('devices', ['id' => $deviceA, 'push_token' => null]);
});

it('requires the X-Device-Id header', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/devices', ['platform' => 'android'], [
        'X-App-Version' => '1.4.0',
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertStatus(422)->assertJsonPath('title', 'Validation failed');
});

it('requires authentication', function () {
    registerDevice($this, ['platform' => 'android'], (string) Str::uuid())
        ->assertStatus(401);
});
