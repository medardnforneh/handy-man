<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Block
 */
final class BlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'party_id' => $this->party_id,
            'blocked_party_id' => $this->blocked_party_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
