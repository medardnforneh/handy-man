<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProviderProfile;
use App\Models\ProviderSkill;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderSkill>
 */
final class ProviderSkillFactory extends Factory
{
    protected $model = ProviderSkill::class;

    /**
     * @return array<model-property<ProviderSkill>, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_profile_id' => ProviderProfile::factory(),
            'skill_id' => Skill::factory(),
            'price_model' => 'fixed',
            'rate_minor' => 500000, // 5,000 XAF
            'currency' => 'XAF',
            'years_experience' => fake()->numberBetween(1, 15),
        ];
    }
}
