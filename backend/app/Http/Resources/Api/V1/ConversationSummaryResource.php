<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Workspace\ConversationSummary;
use App\Domain\Workspace\MessageKind;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConversationSummary
 */
final class ConversationSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $job = $this->conversation->job;
        $last = $this->lastMessage;

        return [
            'id' => $this->conversation->id,
            // The workspace route is keyed by JOB, not conversation — hand the client the id it will
            // actually navigate with rather than one it would have to translate.
            'job_id' => $this->conversation->job_id,
            'reference' => $job?->reference,
            'title' => $job?->title,
            'status' => $job?->status->value,
            'counterpart_name' => $this->counterpartName,
            'unread_count' => $this->unreadCount,
            'last_message' => $last === null ? null : [
                // The KIND travels, not a rendered sentence: a server-narrated message ("quote
                // accepted") must be shown in the reader's own language, and only the client knows
                // which that is. Sending English prose here would hard-code one locale into the API.
                'kind' => $last->kind->value,
                // Only free-form kinds carry a body worth previewing; a structured message's `body`
                // is narration the client re-renders from `kind`.
                'preview' => $last->kind->isClientPostable() && $last->kind !== MessageKind::Voice
                    ? $last->body
                    : null,
                'mine' => $last->sender_user_id === $request->user()?->getKey(),
                'created_at' => $last->created_at->toIso8601String(),
            ],
        ];
    }
}
