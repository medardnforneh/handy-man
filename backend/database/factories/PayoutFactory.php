<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Party;
use App\Models\Payout;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payout>
 */
final class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    /**
     * @return array<model-property<Payout>, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->individual(),
            'amount_minor' => 500_000,
            'currency' => 'XAF',
            'msisdn' => '+2376'.fake()->numerify('########'),
            'gateway' => 'fake',
            'status' => 'pending',
            'external_ref' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
