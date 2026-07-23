<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\EngagementShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EngagementShare>
 */
final class EngagementShareFactory extends Factory
{
    protected $model = EngagementShare::class;

    /**
     * @return array<model-property<EngagementShare>, mixed>
     */
    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'token_hash' => hash('sha256', Str::random(40)),
            'created_by_user_id' => User::factory(),
            'expires_at' => now()->addHours(8),
            'created_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
