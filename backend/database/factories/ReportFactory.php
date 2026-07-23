<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Party;
use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
final class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<model-property<Report>, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_party_id' => Party::factory()->individual(),
            'subject_party_id' => Party::factory()->individual(),
            'category' => 'off_platform',
            'body' => fake()->sentence(),
            'status' => 'open',
            'created_at' => now(),
        ];
    }
}
