<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\PushMessage;
use App\Domain\Notifications\PushSender;
use App\Events\OutboxMessagePublished;
use App\Models\Device;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Turns a relayed `message.created` outbox event into push notifications (build plan P5-05). Push
 * rides the transactional outbox — it fires only for a committed message, and a dead token can't
 * sink the batch. Recipients are the conversation's participants except the message's sender; each
 * is notified in their own comms locale (doc 09). The visible copy is deliberately generic — the
 * `data` payload deep-links the app to the thread, which renders the real content.
 */
final class NotifyOnOutboxMessage
{
    public function __construct(private readonly PushSender $sender) {}

    public function handle(OutboxMessagePublished $event): void
    {
        if ($event->type !== 'message.created') {
            return;
        }

        $messageId = $event->payload['message_id'] ?? null;
        if (! is_string($messageId)) {
            return;
        }

        $message = Message::query()->with('conversation.participants')->find($messageId);
        if ($message === null) {
            return;
        }

        /** @var Collection<int, string> $recipientIds */
        $recipientIds = $message->conversation->participants
            ->pluck('user_id')
            ->reject(fn (string $id): bool => $id === $message->sender_user_id)
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $fallback = (string) config('app.locale', 'en');

        User::query()->whereIn('id', $recipientIds)->get()
            ->groupBy(fn (User $u): string => $u->comms_locale ?: $fallback)
            ->each(function (Collection $group, string $locale) use ($message): void {
                /** @var array<int, string> $tokens */
                $tokens = Device::query()
                    ->whereIn('user_id', $group->pluck('id'))
                    ->whereNotNull('push_token')
                    ->whereNull('revoked_at')
                    ->pluck('push_token')
                    ->all();

                if ($tokens === []) {
                    return;
                }

                $this->sender->send($tokens, new PushMessage(
                    title: (string) __('push.new_message.title', [], $locale),
                    body: (string) __('push.new_message.body', [], $locale),
                    data: [
                        'type' => 'message',
                        'conversation_id' => (string) $message->conversation_id,
                        'kind' => $message->kind->value,
                    ],
                ));
            });
    }
}
