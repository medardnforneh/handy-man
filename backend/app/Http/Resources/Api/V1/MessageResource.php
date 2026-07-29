<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Media;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_user_id' => $this->sender_user_id,
            'kind' => $this->kind->value,
            'body' => $this->body,
            'payload' => $this->payload,
            'contact_flag' => $this->contact_flag,
            'reply_to_id' => $this->reply_to_id,
            // A voice note's audio (P4-05). The URL is the authorized media route — never the raw
            // storage path, which is not fetchable and would leak the layout on disk.
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn (Media $m): array => [
                'id' => $m->id,
                'url' => route('api.v1.media.show', ['media' => $m->id]),
                'bytes' => $m->bytes,
            ])->all()),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
