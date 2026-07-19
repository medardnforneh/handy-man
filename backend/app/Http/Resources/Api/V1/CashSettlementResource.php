<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CashSettlement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashSettlement
 */
final class CashSettlementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'engagement_id' => $this->engagement_id,
            'milestone_id' => $this->milestone_id,
            'amount' => ['amount_minor' => $this->amount_minor, 'currency' => $this->currency],
            'commission' => ['amount_minor' => $this->commission_minor, 'currency' => $this->currency],
            'recorded_at' => $this->recorded_at->toIso8601String(),
        ];
    }
}
