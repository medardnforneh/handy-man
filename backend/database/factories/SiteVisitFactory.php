<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Job;
use App\Models\Party;
use App\Models\SiteVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteVisit>
 */
final class SiteVisitFactory extends Factory
{
    protected $model = SiteVisit::class;

    /**
     * @return array<model-property<SiteVisit>, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'provider_party_id' => Party::factory()->individual(),
            'scheduled_for' => now()->addDay(),
            'is_chargeable' => false,
            'fee_minor' => 0,
            'currency' => 'XAF',
            'status' => 'scheduled',
        ];
    }

    public function chargeable(int $feeMinor = 30000): static
    {
        return $this->state(fn () => ['is_chargeable' => true, 'fee_minor' => $feeMinor]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'completed_at' => now()]);
    }
}
