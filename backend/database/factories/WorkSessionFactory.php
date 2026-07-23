<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\WorkSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * @extends Factory<WorkSession>
 */
final class WorkSessionFactory extends Factory
{
    protected $model = WorkSession::class;

    /**
     * @return array<model-property<WorkSession>, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'started_at' => now(),
            'start_point' => new Point(3.848, 11.502, Srid::WGS84->value), // Yaoundé
            'start_accuracy_m' => 12.0,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'ended_at' => now()->addHour(),
            'end_point' => new Point(3.848, 11.502, Srid::WGS84->value),
            'end_accuracy_m' => 10.0,
        ]);
    }
}
