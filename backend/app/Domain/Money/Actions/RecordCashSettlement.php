<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Engagements\MilestoneStatus;
use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\CashSettlement;
use App\Models\Engagement;
use App\Models\Milestone;
use App\Models\User;
use App\Support\Money;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Record a cash settlement (build plan P3-15, doc 03). The provider was paid in cash off-platform;
 * the platform still earns its commission. We book the commission as revenue and as a debt the
 * provider owes — DR provider_receivable(pro) / CR platform_revenue — and keep the audit row. When a
 * milestone is named, it's marked paid (settled in cash). No escrow is involved.
 */
final class RecordCashSettlement
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Outbox $outbox,
    ) {}

    public function handle(
        User $recorder,
        Engagement $engagement,
        int $amountMinor,
        ?Milestone $milestone = null,
    ): CashSettlement {
        $currency = $engagement->currency;
        $commission = Money::of($amountMinor, $currency)->percentage((int) config('money.commission_bps'))->minor;

        return DB::transaction(function () use ($recorder, $engagement, $amountMinor, $milestone, $currency, $commission): CashSettlement {
            $txn = null;
            if ($commission > 0) {
                $txn = $this->ledger->post(
                    TxnKind::CashSettlement,
                    [
                        LedgerEntryInput::debit($this->ledger->account(AccountKind::ProviderReceivable, $engagement->provider_party_id, $currency), $commission),
                        LedgerEntryInput::credit($this->ledger->account(AccountKind::PlatformRevenue, currency: $currency), $commission),
                    ],
                    referenceType: 'engagement',
                    referenceId: $engagement->id,
                    memo: 'cash settlement commission',
                );
            }

            $settlement = CashSettlement::query()->create([
                'engagement_id' => $engagement->id,
                'milestone_id' => $milestone?->id,
                'party_id' => $engagement->provider_party_id,
                'recorded_by_user_id' => $recorder->id,
                'amount_minor' => $amountMinor,
                'commission_minor' => $commission,
                'currency' => $currency,
                'ledger_transaction_id' => $txn?->id,
                'recorded_at' => now(),
            ]);

            if ($milestone !== null && ! in_array($milestone->status, [MilestoneStatus::Approved, MilestoneStatus::Paid], true)) {
                $milestone->update(['status' => MilestoneStatus::Paid->value, 'approved_at' => now()]);
            }

            $this->outbox->publish('cash.settled', [
                'cash_settlement_id' => $settlement->id,
                'engagement_id' => $engagement->id,
                'amount_minor' => $amountMinor,
                'commission_minor' => $commission,
            ]);

            return $settlement;
        });
    }
}
