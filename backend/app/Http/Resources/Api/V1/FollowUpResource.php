<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\FollowUp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FollowUp
 */
final class FollowUpResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'channel' => $this->channel->value,
            'status' => $this->status->value,
            'scheduled_for' => $this->scheduled_for->toIso8601String(),
            'job_id' => $this->job_id,
            'engagement_id' => $this->engagement_id,
            'quotation_id' => $this->quotation_id,
            'warranty_id' => $this->warranty_id,
            'response_action' => $this->response_action,
        ];
    }
}
