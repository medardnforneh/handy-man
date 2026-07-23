<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Reviews\ReviewVisibility;
use App\Models\Engagement;
use App\Models\Party;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
final class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * @return array<model-property<Review>, mixed>
     */
    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'author_party_id' => Party::factory()->individual(),
            'subject_party_id' => Party::factory()->individual(),
            'rating' => fake()->numberBetween(1, 5),
            'body' => fake()->sentence(),
            'visibility' => ReviewVisibility::Pending->value,
            'submitted_at' => now(),
            'window_closes_at' => now()->addDays(14),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'visibility' => ReviewVisibility::Published->value,
            'published_at' => now(),
        ]);
    }
}
