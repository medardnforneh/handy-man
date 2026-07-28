<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Listeners;

use App\Broadcasting\ChannelAccess;
use App\Domain\Notifications\Listeners\NotifyOnOutboxMessage;
use App\Events\MessagePosted;
use App\Events\OutboxMessagePublished;
use App\Models\Engagement;
use App\Models\Message;

/**
 * Turns a relayed `message.created` outbox event into a live broadcast (build plan P4-04), the
 * sibling of {@see NotifyOnOutboxMessage}. The Narrator's
 * contract says fan-out is driven off the outbox and never inline, so this is the seam: only a
 * COMMITTED message is ever announced.
 *
 * The channel is keyed by the ENGAGEMENT (`engagement.{id}`, authorized by
 * {@see ChannelAccess::isEngagementParticipant}) while the conversation is keyed
 * by the job, so the engagement is resolved here. A conversation without an engagement — which
 * shouldn't happen, since one is created when the engagement forms — is skipped rather than
 * broadcast to a channel nobody can subscribe to.
 */
final class BroadcastOnOutboxMessage
{
    public function handle(OutboxMessagePublished $event): void
    {
        if ($event->type !== 'message.created') {
            return;
        }

        $messageId = $event->payload['message_id'] ?? null;
        if (! is_string($messageId)) {
            return;
        }

        $message = Message::query()->with('conversation')->find($messageId);
        if ($message === null) {
            return;
        }

        $engagement = Engagement::query()
            ->where('job_id', $message->conversation->job_id)
            ->first();

        if ($engagement === null) {
            return;
        }

        MessagePosted::dispatch($message, $engagement->id);
    }
}
