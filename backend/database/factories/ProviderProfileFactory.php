<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Party;
use App\Models\ProviderProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderProfile>
 */
final class ProviderProfileFactory extends Factory
{
    protected $model = ProviderProfile::class;

    /**
     * @return array<model-property<ProviderProfile>, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->individual(),
            'headline' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'bio_language' => 'fr',
            'verification_tier' => 0,
        ];
    }

    public function verified(int $tier = 2): static
    {
        return $this->state(fn () => ['verification_tier' => $tier]);
    }
}
