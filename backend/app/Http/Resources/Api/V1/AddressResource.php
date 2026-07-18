<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
final class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'line1' => $this->line1,
            'quarter' => $this->quarter,
            'city' => $this->city,
            'region' => $this->region,
            'country_code' => $this->country_code,
            'landmark_note' => $this->landmark_note,
            'latitude' => $this->point->latitude,
            'longitude' => $this->point->longitude,
        ];
    }
}
