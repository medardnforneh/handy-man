<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Engagement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
final class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    /**
     * @return array<model-property<Assignment>, mixed>
     */
    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'worker_user_id' => User::factory(),
            'assigned_by_user_id' => User::factory(),
            'role' => 'lead',
            'status' => 'assigned',
            'assigned_at' => now(),
        ];
    }

    public function helper(): static
    {
        return $this->state(fn () => ['role' => 'helper']);
    }
}
