<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\JobReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobReport>
 */
final class JobReportFactory extends Factory
{
    protected $model = JobReport::class;

    /**
     * @return array<model-property<JobReport>, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'summary' => fake()->paragraph(),
            'materials' => [['label' => 'Cement', 'qty' => 2, 'unit_cost_minor' => 350000]],
            'extra_charges_minor' => 0,
            'submitted_at' => now(),
        ];
    }
}
