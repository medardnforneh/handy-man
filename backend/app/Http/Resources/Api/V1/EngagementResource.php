<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Engagement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Engagement
 */
final class EngagementResource extends JsonResource
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
            'offer_id' => $this->offer_id,
            'quotation_id' => $this->quotation_id,
            'agreed_amount' => [
                'amount_minor' => $this->agreed_amount_minor,
                'currency' => $this->currency,
            ],
            'visit_credit' => [
                'amount_minor' => $this->visit_credit_minor,
                'currency' => $this->currency,
            ],
            'is_escrowed' => $this->is_escrowed,
            'accepted_at' => $this->accepted_at->toIso8601String(),
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($a) => [
                'id' => $a->id,
                'worker_user_id' => $a->worker_user_id,
                'role' => $a->role->value,
                'status' => $a->status->value,
            ])),
            'milestones' => $this->whenLoaded('milestones', fn () => $this->milestones->map(fn ($m) => [
                'id' => $m->id,
                'position' => $m->position,
                'title' => $m->title,
                'amount_minor' => $m->amount_minor,
                'status' => $m->status->value,
            ])),
        ];
    }
}
