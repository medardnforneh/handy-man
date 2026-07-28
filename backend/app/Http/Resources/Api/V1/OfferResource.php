<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\JobOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobOffer
 */
final class OfferResource extends JsonResource
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
            'origin' => $this->origin->value,
            'status' => $this->status->value,
            'amount' => $this->amount_minor === null ? null : [
                'amount_minor' => $this->amount_minor,
                'currency' => $this->currency,
            ],
            'message' => $this->message,
            'expires_at' => $this->expires_at->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            // Embedded for the provider's opportunity feed. JobResource minimises PII on its own — a
            // provider viewing a pre-engagement job sees only the coarse area, never the exact address.
            'job' => $this->whenLoaded('job', fn () => JobResource::make($this->job)),
        ];
    }
}
