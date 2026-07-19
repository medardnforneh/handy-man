<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\AccountKind;
use App\Domain\Money\InsufficientLeadCredits;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Spend a provider's prepaid lead credits (build plan P3-07, doc 03) — e.g. sending a bid. Shrinks
 * the liability and books the amount as revenue: DR lead_credit_liability(pro) / CR platform_revenue.
 *
 * Overspend is prevented by locking the provider's credit account row for the check-and-post, so
 * concurrent spends serialize and a balance can never go negative.
 */
final class SpendLeadCredits
{
    public function __construct(private readonly Ledger $ledger) {}

    public function handle(
        User $provider,
        int $amountMinor,
        string $reason,
        ?string $referenceType = null,
        ?string $referenceId = null,
        string $currency = 'XAF',
    ): LedgerTransaction {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Spend amount must be positive.');
        }

        $liability = $this->ledger->account(AccountKind::LeadCreditLiability, $provider->party_id, $currency);
        $revenue = $this->ledger->account(AccountKind::PlatformRevenue, currency: $currency);

        return DB::transaction(function () use ($liability, $revenue, $amountMinor, $reason, $referenceType, $referenceId, $currency): LedgerTransaction {
            // Serialize spends on this account so the balance check can't race.
            LedgerAccount::query()->whereKey($liability->id)->lockForUpdate()->firstOrFail();

            $available = -$liability->balanceMinor(); // credit-normal → owed to the provider

            if ($available < $amountMinor) {
                throw new InsufficientLeadCredits($available, $amountMinor, $currency);
            }

            return $this->ledger->post(
                TxnKind::LeadCreditSpend,
                [
                    LedgerEntryInput::debit($liability, $amountMinor),
                    LedgerEntryInput::credit($revenue, $amountMinor),
                ],
                referenceType: $referenceType,
                referenceId: $referenceId,
                memo: $reason,
            );
        });
    }
}
