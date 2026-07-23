<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Media;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
final class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<model-property<Media>, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_party_id' => Party::factory()->individual(),
            'attachable_type' => 'job_report',
            'attachable_id' => Str::uuid()->toString(),
            'kind' => 'before',
            'storage_path' => 'media/job_report/'.Str::uuid()->toString().'.jpg',
            'sha256' => hash('sha256', Str::random()),
            'bytes' => fake()->numberBetween(1000, 500000),
            'captured_at' => now(),
        ];
    }
}
