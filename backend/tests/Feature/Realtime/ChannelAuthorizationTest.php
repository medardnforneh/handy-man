<?php

declare(strict_types=1);

use App\Broadcasting\ChannelAccess;
use App\Domain\Jobs\JobStatus;
use App\Domain\Quotations\Actions\AcceptQuotation;
use App\Models\Job;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Str;

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

it('authorizes the engagement channel for participants and rejects everyone else (P4-03)', function () {
    // An engaged job has a conversation with the customer + provider as participants.
    $customer = User::factory()->create();
    $job = Job::factory()->remote()->status(JobStatus::Open)->create([
        'customer_party_id' => $customer->party_id,
        'created_by_user_id' => $customer->id,
    ]);
    $provider = User::factory()->create();
    $quote = Quotation::factory()->submitted()->create([
        'job_id' => $job->id, 'provider_party_id' => $provider->party_id,
        'subtotal_minor' => 500_000, 'deposit_minor' => 0, 'valid_until' => now()->addDays(3),
    ]);
    $engagement = app(AcceptQuotation::class)->handle($customer, $quote);

    $stranger = User::factory()->create();

    expect(ChannelAccess::isEngagementParticipant($customer, $engagement->id))->toBeTrue()
        ->and(ChannelAccess::isEngagementParticipant($provider, $engagement->id))->toBeTrue()
        ->and(ChannelAccess::isEngagementParticipant($stranger, $engagement->id))->toBeFalse();
});

it('denies the engagement channel for an unknown engagement id', function () {
    expect(ChannelAccess::isEngagementParticipant(User::factory()->create(), (string) Str::uuid()))->toBeFalse();
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
