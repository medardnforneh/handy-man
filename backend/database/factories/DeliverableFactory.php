<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Deliverable;
use App\Models\Engagement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deliverable>
 */
final class DeliverableFactory extends Factory
{
    protected $model = Deliverable::class;

    /**
     * @return array<model-property<Deliverable>, mixed>
     */
    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'title' => fake()->sentence(3),
            'media_url' => 'deliverables/'.fake()->uuid().'.pdf',
            'status' => 'submitted',
            'submitted_at' => now(),
        ];
    }
}
