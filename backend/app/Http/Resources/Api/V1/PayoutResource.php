<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payout
 */
final class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => ['amount_minor' => $this->amount_minor, 'currency' => $this->currency],
            'msisdn' => $this->msisdn,
            'status' => $this->status->value,
            'external_ref' => $this->external_ref,
            'requested_at' => $this->requested_at->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'failure_code' => $this->failure_code,
        ];
    }
}
