<?php

declare(strict_types=1);

namespace App\Domain\Provider\Actions;

use App\Domain\Access\Capabilities\ListSkill;
use App\Domain\Access\Facts\Fact;
use App\Domain\Access\Facts\FactDeriver;
use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Models\User;

/**
 * Lists a skill on the provider profile (build plan P1-08). Gated on `has_provider_profile` (doc 10)
 * — the ListSkill capability returns a precondition_unmet prompting profile creation if missing.
 * Idempotent per (profile, skill).
 */
final class AddProviderSkill
{
    public function __construct(
        private readonly ListSkill $capability,
        private readonly FactDeriver $facts,
    ) {}

    public function handle(User $user, string $skillId, string $priceModel, ?int $rateMinor, ?int $yearsExperience): ProviderSkill
    {
        $this->capability->authorize($user);

        $profile = ProviderProfile::query()->where('party_id', $user->party_id)->firstOrFail();

        $providerSkill = ProviderSkill::query()->updateOrCreate(
            ['provider_profile_id' => $profile->id, 'skill_id' => $skillId],
            [
                'price_model' => $priceModel,
                'rate_minor' => $rateMinor,
                'currency' => 'XAF',
                'years_experience' => $yearsExperience,
            ],
        );

        $this->facts->forget($user, Fact::SkillListed);

        return $providerSkill;
    }
}
