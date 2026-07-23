<?php

declare(strict_types=1);

use App\Domain\Notifications\FakePushSender;
use App\Domain\Notifications\PushSender;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Job;
use App\Models\Message;
use App\Models\User;
use App\Support\Outbox;
use App\Support\OutboxRelay;

/**
 * P5-05 acceptance (doc 02/06): push rides the transactional outbox. A committed `message.created`
 * event, once relayed, notifies the conversation's other participants (never the sender) on their
 * registered devices — in each recipient's own comms locale.
 */

/**
 * @return array{customer: User, provider: User, conversation: Conversation, message: Message}
 */
function conversationWithMessageFrom(string $senderIsProvider = 'provider'): array
{
    $customer = User::factory()->create(['comms_locale' => 'en']);
    $provider = User::factory()->create(['comms_locale' => 'fr']);
    $job = Job::factory()->create([
        'customer_party_id' => $customer->party_id,
        'created_by_user_id' => $customer->id,
    ]);
    $conversation = Conversation::query()->create(['job_id' => $job->id]);
    $conversation->participants()->create(['party_id' => $customer->party_id, 'user_id' => $customer->id, 'joined_at' => now()]);
    $conversation->participants()->create(['party_id' => $provider->party_id, 'user_id' => $provider->id, 'joined_at' => now()]);

    $sender = $senderIsProvider === 'provider' ? $provider : $customer;
    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_user_id' => $sender->id,
        'kind' => 'text',
        'body' => 'On my way.',
    ]);

    return compact('customer', 'provider', 'conversation', 'message');
}

it('defaults to the fake push sender', function () {
    expect(app(PushSender::class))->toBeInstanceOf(FakePushSender::class)
        ->and(app(PushSender::class)->name())->toBe('fake');
});

it('pushes a relayed message to the other participant, not the sender', function () {
    ['customer' => $customer, 'provider' => $provider, 'conversation' => $conversation, 'message' => $message] =
        conversationWithMessageFrom('provider');

    Device::factory()->create(['user_id' => $customer->id, 'push_token' => 'CUSTOMER_TOKEN']);
    Device::factory()->create(['user_id' => $provider->id, 'push_token' => 'PROVIDER_TOKEN']);

    app(Outbox::class)->publish('message.created', [
        'message_id' => $message->id, 'conversation_id' => $conversation->id, 'kind' => 'text',
    ]);
    app(OutboxRelay::class)->drain();

    /** @var FakePushSender $push */
    $push = app(FakePushSender::class);

    expect($push->allTokens())->toContain('CUSTOMER_TOKEN')
        ->and($push->allTokens())->not->toContain('PROVIDER_TOKEN');
});

it('notifies each recipient in their own comms locale', function () {
    // Customer (en) sends → provider (fr) is notified, in French.
    ['provider' => $provider, 'conversation' => $conversation, 'message' => $message] =
        conversationWithMessageFrom('customer');

    Device::factory()->create(['user_id' => $provider->id, 'push_token' => 'PROVIDER_TOKEN']);

    app(Outbox::class)->publish('message.created', [
        'message_id' => $message->id, 'conversation_id' => $conversation->id, 'kind' => 'text',
    ]);
    app(OutboxRelay::class)->drain();

    /** @var FakePushSender $push */
    $push = app(FakePushSender::class);

    expect($push->sent)->toHaveCount(1)
        ->and($push->sent[0]['message']->title)->toBe('Nouveau message')
        ->and($push->sent[0]['message']->data['conversation_id'])->toBe($conversation->id);
});

it('does nothing when the only participant is the sender', function () {
    ['message' => $message, 'conversation' => $conversation, 'provider' => $provider] = conversationWithMessageFrom('provider');
    // Drop the customer participant so the provider (sender) is the sole participant.
    $conversation->participants()->where('user_id', '!=', $provider->id)->delete();
    Device::factory()->create(['user_id' => $provider->id, 'push_token' => 'PROVIDER_TOKEN']);

    app(Outbox::class)->publish('message.created', [
        'message_id' => $message->id, 'conversation_id' => $conversation->id, 'kind' => 'text',
    ]);
    app(OutboxRelay::class)->drain();

    expect(app(FakePushSender::class)->sent)->toBeEmpty();
});
