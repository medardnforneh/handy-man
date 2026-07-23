<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\Engagement;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispute>
 */
final class DisputeFactory extends Factory
{
    protected $model = Dispute::class;

    /**
     * @return array<model-property<Dispute>, mixed>
     */
    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'raised_by_party_id' => Party::factory()->individual(),
            'category' => 'quality',
            'body' => fake()->paragraph(),
            'status' => 'open',
        ];
    }
}
