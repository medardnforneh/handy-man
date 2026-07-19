<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashSettlement;
use App\Models\Engagement;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSettlement>
 */
final class CashSettlementFactory extends Factory
{
    protected $model = CashSettlement::class;

    /**
     * @return array<model-property<CashSettlement>, mixed>
     */
    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'party_id' => Party::factory()->individual(),
            'recorded_by_user_id' => User::factory(),
            'amount_minor' => 100_000,
            'commission_minor' => 15_000,
            'currency' => 'XAF',
            'recorded_at' => now(),
        ];
    }
}
