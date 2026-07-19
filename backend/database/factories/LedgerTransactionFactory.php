<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerTransaction>
 */
final class LedgerTransactionFactory extends Factory
{
    protected $model = LedgerTransaction::class;

    /**
     * @return array<model-property<LedgerTransaction>, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => 'adjustment',
            'occurred_at' => now(),
        ];
    }
}
