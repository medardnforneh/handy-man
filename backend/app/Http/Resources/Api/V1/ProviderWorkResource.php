<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Engagement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the provider's active-work list (build plan P5-03). The provider is engaged, so the
 * customer's display name is theirs to see — but this is a compact list view: no address, money, or
 * milestone detail (those load on the work-detail screen). Requires `job.customer` eager-loaded.
 *
 * @mixin Engagement
 */
final class ProviderWorkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $job = $this->job;

        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'reference' => $job->reference,
            'title' => $job->title,
            'customer_name' => $job->customer?->display_name,
            'engagement_mode' => $job->engagement_mode->value,
            'job_status' => $job->status->value,
            'accepted_at' => $this->accepted_at->toIso8601String(),
        ];
    }
}
