<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quotation
 */
final class QuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'provider_party_id' => $this->provider_party_id,
            'version' => $this->version,
            'supersedes_id' => $this->supersedes_id,
            'status' => $this->status->value,
            'subtotal' => ['amount_minor' => $this->subtotal_minor, 'currency' => $this->currency],
            'deposit' => ['amount_minor' => $this->deposit_minor, 'currency' => $this->currency],
            'notes' => $this->notes,
            'customer_requested_by' => $this->customer_requested_by?->toIso8601String(),
            'provider_estimated_at' => $this->provider_estimated_at?->toIso8601String(),
            'provider_committed_at' => $this->provider_committed_at?->toIso8601String(),
            'valid_until' => $this->valid_until->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'position' => $line->position,
                'kind' => $line->kind,
                'label' => $line->label,
                'quantity' => $line->quantity,
                'unit_price_minor' => $line->unit_price_minor,
            ])),
        ];
    }
}
