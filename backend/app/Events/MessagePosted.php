<?php

declare(strict_types=1);

namespace App\Events;

use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A message landed in an engagement's thread — pushed live to the participants (build plan P4-04).
 *
 * Dispatched from the outbox relay, never from the Action that wrote the message: the Narrator
 * writes inside the transition's transaction (rule #11), so broadcasting there would announce
 * messages that a rollback then erased. Riding the outbox means only committed messages are ever
 * broadcast.
 *
 * `ShouldBroadcastNow` because the relay is already a background worker — queueing the broadcast a
 * second time would add latency to a chat message for no benefit.
 *
 * Reverb is NOT the source of truth (CLAUDE.md stack table): this payload exists so a live client
 * can render immediately, and the REST thread stays authoritative on reconnect (P4-07).
 */
final class MessagePosted implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly Message $message,
        public readonly string $engagementId,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('engagement.'.$this->engagementId)];
    }

    public function broadcastAs(): string
    {
        return 'message.posted';
    }

    /**
     * The same shape {@see MessageResource} sends, so a client renders a
     * broadcast message with exactly the code path it uses for a fetched one.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_user_id' => $this->message->sender_user_id,
            'kind' => $this->message->kind->value,
            'body' => $this->message->body,
            'payload' => $this->message->payload,
            'contact_flag' => $this->message->contact_flag,
            'reply_to_id' => $this->message->reply_to_id,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
