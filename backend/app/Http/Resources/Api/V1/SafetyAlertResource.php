<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\SafetyAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SafetyAlert
 */
final class SafetyAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'status' => $this->status,
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
