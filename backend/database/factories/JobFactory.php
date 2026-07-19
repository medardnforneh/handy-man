<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Jobs\EngagementMode;
use App\Domain\Jobs\JobStatus;
use App\Models\Address;
use App\Models\Job;
use App\Models\Party;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Job>
 */
final class JobFactory extends Factory
{
    protected $model = Job::class;

    /**
     * @return array<model-property<Job>, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'JOB-'.Str::upper(Str::random(5)),
            'customer_party_id' => Party::factory()->individual(),
            'created_by_user_id' => User::factory(),
            'skill_id' => Skill::factory(),
            'address_id' => Address::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'description_language' => 'fr',
            'engagement_mode' => EngagementMode::Onsite->value,
            'assignment_mode' => 'direct',
            'price_model' => 'quote_only',
            'status' => JobStatus::Draft->value,
            'currency' => 'XAF',
            'urgency' => 1,
        ];
    }

    public function remote(): static
    {
        return $this->state(fn () => [
            'engagement_mode' => EngagementMode::Remote->value,
            'address_id' => null, // remote work has no address
        ]);
    }

    public function hybrid(): static
    {
        return $this->state(fn () => ['engagement_mode' => EngagementMode::Hybrid->value]);
    }

    public function status(JobStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }
}
