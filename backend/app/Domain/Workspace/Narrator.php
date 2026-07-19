<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use App\Models\Conversation;
use App\Models\Message;
use App\Support\Outbox;

/**
 * The server's voice in the conversation (CLAUDE.md rule #11, doc 06). Domain Actions call this,
 * inside their own transaction, to narrate a state transition as a structured message — so the chat
 * and the state never diverge, and a rolled-back transaction narrates nothing. A client can never
 * produce these; the message endpoint only accepts free-form kinds.
 */
final class Narrator
{
    public function __construct(private readonly Outbox $outbox) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function narrate(Conversation $conversation, MessageKind $kind, array $payload = [], ?string $senderUserId = null): Message
    {
        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_user_id' => $senderUserId,
            'kind' => $kind->value,
            'payload' => $payload === [] ? null : $payload,
        ]);

        // Broadcast/fan-out is driven off the outbox (Reverb, notifications) — never inline.
        $this->outbox->publish('message.created', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'kind' => $kind->value,
        ]);

        return $message;
    }
}
