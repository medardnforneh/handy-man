<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OtpChallenge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OtpChallenge>
 */
final class OtpChallengeFactory extends Factory
{
    protected $model = OtpChallenge::class;

    /**
     * @return array<model-property<OtpChallenge>, mixed>
     */
    public function definition(): array
    {
        return [
            'phone_e164' => '+2376'.fake()->unique()->numerify('########'),
            'code_hash' => Hash::make('123456'),
            'purpose' => 'login',
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }

    public function consumed(): static
    {
        return $this->state(fn () => ['consumed_at' => now()]);
    }
}
