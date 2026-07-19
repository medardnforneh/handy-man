<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\Job;
use App\Models\JobOffer;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Engagement>
 */
final class EngagementFactory extends Factory
{
    protected $model = Engagement::class;

    /**
     * @return array<model-property<Engagement>, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'provider_party_id' => Party::factory()->individual(),
            'offer_id' => JobOffer::factory(),
            'agreed_amount_minor' => 800000,
            'currency' => 'XAF',
            'accepted_at' => now(),
        ];
    }
}
