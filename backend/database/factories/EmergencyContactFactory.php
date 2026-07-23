<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EmergencyContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmergencyContact>
 */
final class EmergencyContactFactory extends Factory
{
    protected $model = EmergencyContact::class;

    /**
     * @return array<model-property<EmergencyContact>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'phone_e164' => '+2376'.fake()->numerify('########'),
            'created_at' => now(),
        ];
    }
}
