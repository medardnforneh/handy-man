<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Party;
use App\Models\Warranty;
use App\Models\WarrantyClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarrantyClaim>
 */
final class WarrantyClaimFactory extends Factory
{
    protected $model = WarrantyClaim::class;

    /**
     * @return array<model-property<WarrantyClaim>, mixed>
     */
    public function definition(): array
    {
        return [
            'warranty_id' => Warranty::factory(),
            'claimed_by_party_id' => Party::factory()->individual(),
            'description' => fake()->sentence(),
            'status' => 'open',
        ];
    }
}
