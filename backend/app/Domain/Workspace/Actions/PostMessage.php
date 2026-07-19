<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Actions;

use App\Domain\Workspace\MessageKind;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * A participant posts a free-form message (build plan P4-01). Only free-form kinds reach here —
 * structured messages are narrated by the server (rule #11). Contact details (phone/email) are
 * detected and flagged for the record but NOT blocked in v1 (P4-09): the platform observes leakage
 * rather than fighting the user.
 */
final class PostMessage
{
    public function __construct(private readonly Outbox $outbox) {}

    public function handle(User $sender, Conversation $conversation, string $body, ?string $replyToId = null): Message
    {
        return DB::transaction(function () use ($sender, $conversation, $body, $replyToId): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_user_id' => $sender->id,
                'body' => $body,
                'kind' => MessageKind::Text->value,
                'contact_flag' => $this->detectContact($body),
                'reply_to_id' => $replyToId,
            ]);

            $this->outbox->publish('message.created', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'kind' => MessageKind::Text->value,
            ]);

            return $message;
        });
    }

    /**
     * Detect a phone number or email in the body — logged, never blocked (P4-09).
     */
    private function detectContact(string $body): ?string
    {
        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/', $body) === 1) {
            return 'email';
        }
        if (preg_match('/\+?\d[\d\s().-]{7,}\d/', $body) === 1) {
            return 'phone';
        }

        return null;
    }
}
