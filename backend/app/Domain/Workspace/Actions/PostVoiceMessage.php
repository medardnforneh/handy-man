<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Actions;

use App\Domain\Media\StoreMedia;
use App\Domain\Workspace\MessageKind;
use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\User;
use App\Support\Outbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * A participant posts a voice note (build plan P4-05, doc 06).
 *
 * Voice matters here more than it would elsewhere: typing a description of a broken pipe in a
 * second language is a real barrier, and speaking it is not. The message is a first-class thread
 * entry of kind `voice` with the audio attached as {@see Media}, so it rides every rail
 * text already does — push, live broadcast, and the REST thread — without special cases.
 *
 * Message and media are written in ONE transaction: a half-stored voice note (a row pointing at a
 * file that isn't there, or vice versa) is worse than a failed send.
 */
final class PostVoiceMessage
{
    public function __construct(
        private readonly StoreMedia $storeMedia,
        private readonly Outbox $outbox,
    ) {}

    public function handle(
        User $sender,
        Conversation $conversation,
        UploadedFile $audio,
        ?int $durationMs = null,
    ): Message {
        return DB::transaction(function () use ($sender, $conversation, $audio, $durationMs): Message {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_user_id' => $sender->id,
                'kind' => MessageKind::Voice->value,
                // Duration rides the payload so a client can render the bubble's length without
                // downloading the audio first.
                'payload' => $durationMs === null ? null : ['duration_ms' => $durationMs],
            ]);

            $this->storeMedia->handle(
                file: $audio,
                ownerPartyId: $sender->party_id,
                attachableType: 'message',
                attachableId: $message->id,
                kind: 'attachment',
                disk: (string) config('filesystems.default'),
            );

            $this->outbox->publish('message.created', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'kind' => MessageKind::Voice->value,
            ]);

            return $message;
        });
    }
}
