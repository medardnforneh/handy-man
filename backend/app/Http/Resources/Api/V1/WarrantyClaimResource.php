<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\WarrantyClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WarrantyClaim
 */
final class WarrantyClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warranty_id' => $this->warranty_id,
            'description' => $this->description,
            'status' => $this->status,
            'remedy_job_id' => $this->remedy_job_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
