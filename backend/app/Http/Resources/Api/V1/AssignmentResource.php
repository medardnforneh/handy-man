<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Assignment
 */
final class AssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'engagement_id' => $this->engagement_id,
            'worker_user_id' => $this->worker_user_id,
            'assigned_by_user_id' => $this->assigned_by_user_id,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'assigned_at' => $this->assigned_at->toIso8601String(),
            'removed_at' => $this->removed_at?->toIso8601String(),
            'scheduled_from' => $this->scheduled_from?->toIso8601String(),
            'scheduled_to' => $this->scheduled_to?->toIso8601String(),
        ];
    }
}
