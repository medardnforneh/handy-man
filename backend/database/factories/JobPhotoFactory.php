<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Job;
use App\Models\JobPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPhoto>
 */
final class JobPhotoFactory extends Factory
{
    protected $model = JobPhoto::class;

    /**
     * @return array<model-property<JobPhoto>, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'path' => 'jobs/'.fake()->uuid().'.jpg',
            'position' => 0,
            'created_at' => now(),
        ];
    }
}
