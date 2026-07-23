<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\EmergencyContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmergencyContact
 */
final class EmergencyContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_e164' => $this->phone_e164,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
