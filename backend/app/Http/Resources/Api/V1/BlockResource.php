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
            // Something the blocker can recognise. A list of bare UUIDs is not one anyone can act
            // on, and "unblock this id" is not a decision a person can make. Provider headline
            // where there is one, display name otherwise — the same thing they saw when they
            // blocked, and never more than that (P2-03).
            'blocked_label' => $this->whenLoaded('blockedParty', fn () => $this->label()),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function label(): ?string
    {
        $party = $this->blockedParty;
        if ($party === null) {
            return null;
        }

        $headline = $party->relationLoaded('providerProfile') ? $party->providerProfile?->headline : null;

        return $headline ?? $party->display_name;
    }
}
