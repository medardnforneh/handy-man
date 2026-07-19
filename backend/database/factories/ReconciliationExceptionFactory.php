<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReconciliationException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationException>
 */
final class ReconciliationExceptionFactory extends Factory
{
    protected $model = ReconciliationException::class;

    /**
     * @return array<model-property<ReconciliationException>, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => 'settlement_mismatch',
            'detail' => 'Ledger platform_cash does not match the wallet balance.',
            'amount_minor' => 5_000,
            'status' => 'open',
            'detected_at' => now(),
        ];
    }
}
