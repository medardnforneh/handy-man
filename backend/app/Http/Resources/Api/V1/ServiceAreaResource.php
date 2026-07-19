<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ServiceArea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceArea
 */
final class ServiceAreaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'latitude' => $this->center->latitude,
            'longitude' => $this->center->longitude,
            'radius_m' => $this->radius_m,
        ];
    }
}
