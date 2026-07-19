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
