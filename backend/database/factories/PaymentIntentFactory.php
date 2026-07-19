<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Party;
use App\Models\PaymentIntent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentIntent>
 */
final class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    /**
     * @return array<model-property<PaymentIntent>, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->individual(),
            'purpose' => 'lead_credits',
            'gateway' => 'fake',
            'amount_minor' => 1_000_000,
            'currency' => 'XAF',
            'msisdn' => '+2376'.fake()->numerify('########'),
            'status' => 'pending',
            'external_ref' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(15),
        ];
    }

    public function escrow(): static
    {
        return $this->state(fn () => ['purpose' => 'escrow']);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subMinute()]);
    }
}
