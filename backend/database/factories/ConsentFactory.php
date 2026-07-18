<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Consent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consent>
 */
final class ConsentFactory extends Factory
{
    protected $model = Consent::class;

    /**
     * @return array<model-property<Consent>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'purpose' => 'terms',
            'granted' => true,
            'policy_version' => config('consent.policy_version'),
            'presented_locale' => 'fr',
            'created_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['granted' => false]);
    }

    public function purpose(string $purpose): static
    {
        return $this->state(fn () => ['purpose' => $purpose]);
    }
}
