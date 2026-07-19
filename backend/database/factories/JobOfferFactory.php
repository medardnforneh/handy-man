<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Job;
use App\Models\JobOffer;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobOffer>
 */
final class JobOfferFactory extends Factory
{
    protected $model = JobOffer::class;

    /**
     * @return array<model-property<JobOffer>, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'provider_party_id' => Party::factory()->individual(),
            'origin' => 'customer_direct',
            'status' => 'pending',
            'amount_minor' => 500000,
            'currency' => 'XAF',
            'expires_at' => now()->addHours(48),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => 'accepted', 'responded_at' => now()]);
    }
}
