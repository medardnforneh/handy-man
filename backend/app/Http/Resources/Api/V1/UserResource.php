<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->whenLoaded('party', fn () => $this->party->display_name),
            'phone_e164' => $this->phone_e164,
            'email' => $this->email,
            'locale' => $this->locale,
            'comms_locale' => $this->comms_locale,
            'status' => $this->status,
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
        ];
    }
}
