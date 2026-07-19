<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\SiteVisit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SiteVisit
 */
final class SiteVisitResource extends JsonResource
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
            'scheduled_for' => $this->scheduled_for->toIso8601String(),
            'is_chargeable' => $this->is_chargeable,
            'fee' => ['amount_minor' => $this->fee_minor, 'currency' => $this->currency],
            'status' => $this->status->value,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'outcome_notes' => $this->outcome_notes,
            'resulting_quotation_id' => $this->resulting_quotation_id,
        ];
    }
}
