<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<model-property<User>, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->individual(),
            'phone_e164' => '+2376'.fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => 'password', // hashed by the model cast
            'locale' => 'fr',
            'comms_locale' => 'fr',
            'status' => 'active',
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'phone_verified_at' => null,
            'email_verified_at' => null,
            'status' => 'pending',
        ]);
    }

    public function anglophone(): static
    {
        return $this->state(fn () => ['locale' => 'en', 'comms_locale' => 'en']);
    }
}
