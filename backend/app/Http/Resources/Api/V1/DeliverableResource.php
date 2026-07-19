<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Deliverable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Deliverable
 */
final class DeliverableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'engagement_id' => $this->engagement_id,
            'milestone_id' => $this->milestone_id,
            'title' => $this->title,
            'media_url' => $this->media_url,
            'status' => $this->status->value,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reject_reason' => $this->reject_reason,
        ];
    }
}
