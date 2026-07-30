<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Support\RequestLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A provider as an anonymous or pre-engagement viewer may see them (doc 08).
 *
 * This is a SEPARATE resource from `ProviderProfileResource` on purpose, not duplication for its own
 * sake. That one exposes `display_name` conditionally, on whether the `party` relation happens to be
 * loaded — safe today, but one stray `->with('party')` in a future query would silently start
 * leaking real names onto a public endpoint. Here the name simply has no code path: there is nothing
 * to remember not to do.
 *
 * What a stranger gets is the public HEADLINE, the verification badge, the shrunk rating and the
 * trades offered. Never a person's name, never a service area, never a coordinate.
 */
final class PublicProviderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProviderProfile $profile */
        $profile = $this->resource;
        $locale = RequestLocale::for($request);

        return [
            // The PARTY id: the handle every other public endpoint takes (metrics, reviews) and what
            // an offer is addressed to. Not a leak — acting on a provider requires it by design.
            'party_id' => $profile->party_id,
            'headline' => $profile->headline,
            'bio' => $profile->bio,
            'verification_tier' => $profile->verification_tier,
            // Bayesian-shrunk (P6-09) — never a bare "5.0 (1 review)".
            'rating_avg' => $profile->rating_avg,
            'rating_count' => $profile->rating_count,
            'jobs_completed' => $profile->jobs_completed,
            // Whether they travel to the customer — a yes/no about the kind of work, never where
            // they are. The service areas themselves stay private (P2-03).
            'serves_onsite' => ($profile->service_areas_count ?? 0) > 0,
            'skills' => $profile->relationLoaded('skills')
                ? $profile->skills->map(fn (ProviderSkill $s): array => [
                    'skill_id' => $s->skill_id,
                    'name' => $s->relationLoaded('skill') ? $s->skill->name($locale) : null,
                ])->all()
                : [],
        ];
    }
}
