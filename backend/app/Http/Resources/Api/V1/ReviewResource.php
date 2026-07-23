<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Reviews\ReviewVisibility;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
final class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $published = $this->visibility === ReviewVisibility::Published;

        // Before reveal, the body/rating stay hidden even from an API peek — only the author's own
        // pending submission echoes its content back (they wrote it). The private_note is never
        // exposed here; it is for the subject's eyes through a dedicated surface.
        return [
            'id' => $this->id,
            'engagement_id' => $this->engagement_id,
            'author_party_id' => $this->author_party_id,
            'subject_party_id' => $this->subject_party_id,
            'visibility' => $this->visibility->value,
            'rating' => $published ? $this->rating : null,
            'body' => $published ? $this->body : null,
            'submitted_at' => $this->submitted_at->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'window_closes_at' => $this->window_closes_at->toIso8601String(),
        ];
    }
}
