<?php

declare(strict_types=1);

use App\Broadcasting\ChannelAccess;
use App\Models\User;

/**
 * P0-12 (realtime part): private Reverb channels reject non-participants. The authorization rules
 * (App\Broadcasting\ChannelAccess) are unit-tested directly — deterministic and independent of the
 * broadcasting transport — and one HTTP smoke test confirms the endpoint enforces them.
 */
it('authorizes a user only for their own channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    expect(ChannelAccess::ownsUserChannel($user, (string) $user->id))->toBeTrue()
        ->and(ChannelAccess::ownsUserChannel($user, (string) $other->id))->toBeFalse();
});

it('denies the engagement channel to everyone until participant checks exist', function () {
    $user = User::factory()->create();

    expect(ChannelAccess::isEngagementParticipant($user, '1'))->toBeFalse();
});

it('rejects an unauthenticated subscription at the broadcasting endpoint', function () {
    // Use the Reverb (Pusher-protocol) broadcaster — the default `null` test driver authorizes
    // everything and would not enforce the channel rules.
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb', [
        'driver' => 'reverb',
        'key' => 'testkey',
        'secret' => 'testsecret',
        'app_id' => 'testapp',
        'options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
    ]);

    // No acting user → the endpoint must refuse a private channel (not leak it).
    $this->postJson('/broadcasting/auth', [
        'channel_name' => 'private-user.1',
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});
