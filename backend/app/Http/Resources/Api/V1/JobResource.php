<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Engagements\MilestoneStatus;
use App\Domain\Jobs\EngagementModePolicy;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Job
 *
 * PII minimisation (build plan P2-03, CLAUDE.md rule #6): the EXACT address and any fine location
 * are exposed ONLY to the customer who owns the job (and, once it exists, an engaged provider).
 * A provider browsing the board pre-engagement sees a COARSE area (quarter + city) — never the
 * street line, landmark, or exact coordinates. Whether a job has a location at all is decided by
 * the EngagementModePolicy, not an inline mode check.
 */
final class JobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $full = $this->viewerSeesFullDetails($request->user());

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->title,
            'description' => $this->description,
            'skill_id' => $this->skill_id,
            'engagement_mode' => $this->engagement_mode->value,
            'assignment_mode' => $this->assignment_mode->value,
            'status' => $this->status->value,
            'price_model' => $this->price_model,
            'budget' => $this->budget_minor === null ? null : [
                'amount_minor' => $this->budget_minor,
                'currency' => $this->currency,
            ],
            'urgency' => $this->urgency,
            'requires_verified_provider' => $this->requires_verified_provider,
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->pluck('path')),
            'location' => $this->location($full),
            // A compact engagement summary for the owner's job list (provider + progress + money),
            // so a client doesn't need a second round-trip per job. Null until the job is engaged.
            'engagement' => $full ? $this->engagementSummary() : null,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function viewerSeesFullDetails(?User $viewer): bool
    {
        // The owner sees everything. (Engaged providers are added when engagements land in P2-07.)
        return $viewer !== null && $this->customer_party_id === $viewer->party_id;
    }

    /**
     * Compact engagement summary for the owner's list — provider name, agreed money and milestone
     * progress. Requires `engagement.provider` + `engagement.milestones` to be eager-loaded.
     *
     * @return array<string, mixed>|null
     */
    private function engagementSummary(): ?array
    {
        $engagement = $this->engagement;
        if ($engagement === null) {
            return null;
        }

        $milestones = $engagement->milestones;
        $done = $milestones->where('status', MilestoneStatus::Paid->value)->count();

        return [
            'provider_name' => $engagement->provider?->display_name,
            'agreed_amount_minor' => $engagement->agreed_amount_minor,
            'currency' => $engagement->currency,
            'milestones_done' => $done,
            'milestones_total' => $milestones->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function location(bool $full): ?array
    {
        // Remote (and any mode without an address) has no location to show.
        if (! app(EngagementModePolicy::class)->requiresAddress($this->engagement_mode)) {
            return null;
        }

        $address = $this->address;
        if ($address === null) {
            return null;
        }

        // Coarse area — always safe to show.
        $coarse = [
            'quarter' => $address->quarter,
            'city' => $address->city,
            'region' => $address->region,
            'country_code' => $address->country_code,
        ];

        if (! $full) {
            return $coarse;
        }

        // Full — exact address, only for the owner / engaged provider.
        return array_merge($coarse, [
            'line1' => $address->line1,
            'landmark_note' => $address->landmark_note,
            'latitude' => $address->point->latitude,
            'longitude' => $address->point->longitude,
        ]);
    }
}
