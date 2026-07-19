<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
final class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    /**
     * @return array<model-property<Conversation>, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
        ];
    }
}
