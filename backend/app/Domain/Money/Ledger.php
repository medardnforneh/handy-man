<?php

declare(strict_types=1);

namespace App\Domain\Money;

use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The one way money moves (doc 03). Every posting is a balanced double-entry transaction written
 * atomically; the DB guarantees balance and append-only, this service guarantees the shape and gives
 * a clear error before the DB does. No code outside `App\Domain\Money` should write ledger rows
 * directly — always go through {@see post()}.
 */
final class Ledger
{
    /**
     * Resolve (find or create) the account for a kind. Platform/engagement-scoped kinds ignore the
     * party; party-scoped kinds (provider_payable, lead_credit_liability, promo_liability) require one.
     */
    public function account(AccountKind $kind, ?string $partyId = null, string $currency = Money::XAF): LedgerAccount
    {
        if ($kind->requiresParty() && $partyId === null) {
            throw new InvalidArgumentException("Account kind {$kind->value} requires a party_id.");
        }

        return LedgerAccount::query()->firstOrCreate([
            'party_id' => $kind->requiresParty() ? $partyId : null,
            'kind' => $kind->value,
            'currency' => $currency,
        ]);
    }

    /**
     * The positive amount held in an account, read in its natural direction: a debit-normal account
     * (asset) returns its debit balance; a credit-normal account (a liability like lead credits, or
     * revenue) returns the amount owed/earned. Zero when the account doesn't exist yet.
     */
    public function availableMinor(AccountKind $kind, ?string $partyId = null, string $currency = Money::XAF): int
    {
        $account = LedgerAccount::query()->firstWhere([
            'party_id' => $kind->requiresParty() ? $partyId : null,
            'kind' => $kind->value,
            'currency' => $currency,
        ]);

        if ($account === null) {
            return 0;
        }

        $balance = $account->balanceMinor();

        return $kind->isDebitNormal() ? $balance : -$balance;
    }

    /**
     * Post a balanced transaction. Throws {@see UnbalancedTransaction} before touching the DB if the
     * legs don't balance.
     *
     * @param  list<LedgerEntryInput>  $entries
     */
    public function post(
        TxnKind $kind,
        array $entries,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $memo = null,
        ?string $createdByUserId = null,
    ): LedgerTransaction {
        $this->assertBalanced($entries);

        return DB::transaction(function () use ($kind, $entries, $referenceType, $referenceId, $memo, $createdByUserId): LedgerTransaction {
            $txn = LedgerTransaction::query()->create([
                'kind' => $kind->value,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'memo' => $memo,
                'created_by_user_id' => $createdByUserId,
            ]);

            foreach ($entries as $entry) {
                $txn->entries()->create([
                    'account_id' => $entry->account->id,
                    'direction' => $entry->direction->value,
                    'amount_minor' => $entry->amountMinor,
                ]);
            }

            return $txn;
        });
    }

    /**
     * @param  list<LedgerEntryInput>  $entries
     */
    private function assertBalanced(array $entries): void
    {
        if (count($entries) < 2) {
            throw new UnbalancedTransaction('A ledger transaction needs at least two entries.');
        }

        $debits = 0;
        $credits = 0;
        foreach ($entries as $entry) {
            if ($entry->amountMinor <= 0) {
                throw new UnbalancedTransaction('Entry amounts must be strictly positive.');
            }
            if ($entry->direction === EntryDirection::Debit) {
                $debits += $entry->amountMinor;
            } else {
                $credits += $entry->amountMinor;
            }
        }

        if ($debits !== $credits) {
            throw new UnbalancedTransaction("Unbalanced transaction: debits={$debits} credits={$credits}.");
        }
    }
}
