<?php

declare(strict_types=1);

namespace App\Domain\Money;

use App\Models\LedgerAccount;

/**
 * One leg of a posting handed to {@see Ledger::post()}. Amount is always positive; the direction is
 * explicit. Use {@see debit()} / {@see credit()} at call sites so the intent reads clearly.
 */
final readonly class LedgerEntryInput
{
    public function __construct(
        public LedgerAccount $account,
        public EntryDirection $direction,
        public int $amountMinor,
    ) {}

    public static function debit(LedgerAccount $account, int $amountMinor): self
    {
        return new self($account, EntryDirection::Debit, $amountMinor);
    }

    public static function credit(LedgerAccount $account, int $amountMinor): self
    {
        return new self($account, EntryDirection::Credit, $amountMinor);
    }
}
