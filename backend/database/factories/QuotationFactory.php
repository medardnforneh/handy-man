<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Job;
use App\Models\Party;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
final class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    /**
     * @return array<model-property<Quotation>, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'provider_party_id' => Party::factory()->individual(),
            'version' => 1,
            'status' => 'draft',
            'currency' => 'XAF',
            'subtotal_minor' => 500000,
            'deposit_minor' => 100000,
            'valid_until' => now()->addDays(7),
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => 'submitted', 'submitted_at' => now()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => 'accepted', 'submitted_at' => now(), 'responded_at' => now()]);
    }
}
