<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
final class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<model-property<Organization>, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->organization(),
            'legal_name' => fake()->company().' SARL',
            'rccm_number' => 'RC/'.fake()->numerify('####/####'),
            'niu' => fake()->bothify('P#########?'),
        ];
    }
}
