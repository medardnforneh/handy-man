<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Reference API Resource (P0-05). Resources are the ONLY place response shapes are defined — a
 * controller never returns a raw model. Timestamps are ISO-8601 UTC with offset; the client
 * localises (CLAUDE.md "API conventions").
 *
 * @mixin Note
 */
final class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_id' => $this->author_id,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
