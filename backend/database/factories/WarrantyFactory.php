<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Warranties\WarrantyStatus;
use App\Models\Engagement;
use App\Models\Warranty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warranty>
 */
final class WarrantyFactory extends Factory
{
    protected $model = Warranty::class;

    /**
     * @return array<model-property<Warranty>, mixed>
     */
    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'duration_days' => 90,
            'starts_at' => now(),
            'expires_at' => now()->addDays(90),
            'status' => WarrantyStatus::Active->value,
        ];
    }
}
