<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Money\Ledger;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerEntry>
 *
 * Note: real postings go through {@see Ledger}, which writes balanced pairs. This
 * factory makes a single entry for low-level tests only; a lone entry would fail the deferred
 * balance constraint at commit.
 */
final class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    /**
     * @return array<model-property<LedgerEntry>, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => LedgerTransaction::factory(),
            'account_id' => LedgerAccount::factory(),
            'direction' => 'debit',
            'amount_minor' => 1000,
        ];
    }
}
