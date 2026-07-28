<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProviderProfile
 */
final class ProviderProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // The PARTY id, not the profile row's — this is the handle every other endpoint takes
            // (offers, public metrics, published reviews), so a client that only has this resource
            // can still act. It is not a leak: sending an offer requires it by design.
            'party_id' => $this->party_id,
            'display_name' => $this->whenLoaded('party', fn () => $this->party->display_name),
            'headline' => $this->headline,
            'bio' => $this->bio,
            'bio_language' => $this->bio_language,
            'verification_tier' => $this->verification_tier,
            'rating_avg' => $this->rating_avg,
            'rating_count' => $this->rating_count,
            'jobs_completed' => $this->jobs_completed,
            'accepts_direct' => $this->accepts_direct,
            'accepts_dispatch' => $this->accepts_dispatch,
            'accepts_bidding' => $this->accepts_bidding,
            'skills' => ProviderSkillResource::collection($this->whenLoaded('skills')),
            'service_areas' => ServiceAreaResource::collection($this->whenLoaded('serviceAreas')),
        ];
    }
}
