<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Warranty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Warranty
 */
final class WarrantyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'engagement_id' => $this->engagement_id,
            'duration_days' => $this->duration_days,
            'starts_at' => $this->starts_at->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'status' => $this->status->value,
            'terms' => $this->terms,
        ];
    }
}
