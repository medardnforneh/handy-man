<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Party>
 */
final class PartyFactory extends Factory
{
    protected $model = Party::class;

    /**
     * @return array<model-property<Party>, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => Party::KIND_INDIVIDUAL,
            'display_name' => fake()->name(),
            'status' => 'active',
        ];
    }

    public function individual(): static
    {
        return $this->state(fn () => ['kind' => Party::KIND_INDIVIDUAL, 'display_name' => fake()->name()]);
    }

    public function organization(): static
    {
        return $this->state(fn () => ['kind' => Party::KIND_ORGANIZATION, 'display_name' => fake()->company()]);
    }
}
