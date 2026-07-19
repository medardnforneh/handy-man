<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

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
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
