<?php

declare(strict_types=1);

use App\Domain\FollowUps\Actions\DispatchFollowUps;
use App\Domain\FollowUps\ChannelLadder;
use App\Domain\FollowUps\FollowUpChannel;
use App\Domain\FollowUps\FollowUpKind;
use App\Domain\FollowUps\FollowUpScheduler;
use App\Domain\Notifications\FakePushSender;
use App\Domain\Notifications\FakeWhatsAppSender;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * P7-05 / P7-06 acceptance (doc 07): the WhatsApp channel and the routing ladder. A dispatched
 * follow-up is delivered on its channel through the (fake) transport, in the target's comms locale,
 * deep-linked back to the follow-up.
 */
it('delivers a WhatsApp follow-up through the WhatsApp transport (P7-05)', function () {
    $user = User::factory()->create(['comms_locale' => 'fr']);

    app(FollowUpScheduler::class)->schedule(
        FollowUpKind::ReviewRequest, $user, FollowUpChannel::WhatsApp, now(), 'engagement', (string) Str::uuid()
    );
    app(DispatchFollowUps::class)->handle();

    /** @var FakeWhatsAppSender $wa */
    $wa = app(FakeWhatsAppSender::class);
    expect($wa->sent)->toHaveCount(1)
        ->and($wa->sent[0]['to'])->toBe($user->phone_e164)
        ->and($wa->sent[0]['template'])->toBe('review_request')
        ->and($wa->sent[0]['locale'])->toBe('fr')
        ->and($wa->sent[0]['deep_link'])->toContain('/follow-up/');
});

it('delivers a push follow-up to the device token (P7-06)', function () {
    $user = User::factory()->create();
    Device::factory()->create(['user_id' => $user->id, 'push_token' => 'DEVICE_TOKEN']);

    app(FollowUpScheduler::class)->schedule(
        FollowUpKind::ReviewRequest, $user, FollowUpChannel::Push, now(), 'engagement', (string) Str::uuid()
    );
    app(DispatchFollowUps::class)->handle();

    expect(app(FakePushSender::class)->allTokens())->toContain('DEVICE_TOKEN');
});

it('picks push when the user has a live device token, else WhatsApp (P7-06 ladder)', function () {
    $withDevice = User::factory()->create();
    Device::factory()->create(['user_id' => $withDevice->id, 'push_token' => 'T']);
    $withoutDevice = User::factory()->create();

    $ladder = app(ChannelLadder::class);
    expect($ladder->pick($withDevice))->toBe(FollowUpChannel::Push)
        ->and($ladder->pick($withoutDevice))->toBe(FollowUpChannel::WhatsApp);
});
