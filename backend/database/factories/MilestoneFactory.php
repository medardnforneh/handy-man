<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\Milestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
final class MilestoneFactory extends Factory
{
    protected $model = Milestone::class;

    /**
     * @return array<model-property<Milestone>, mixed>
     */
    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'position' => 0,
            'title' => fake()->words(2, true),
            'amount_minor' => 0,
            'status' => 'pending',
        ];
    }
}
