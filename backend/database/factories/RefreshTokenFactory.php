<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RefreshToken>
 */
final class RefreshTokenFactory extends Factory
{
    protected $model = RefreshToken::class;

    /**
     * @return array<model-property<RefreshToken>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'family_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}
