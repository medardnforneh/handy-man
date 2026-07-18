<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
final class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'app_version' => $this->app_version,
            'has_push_token' => $this->push_token !== null,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
