<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\PaymentStatus;
use App\Domain\Money\TxnKind;
use App\Models\Payout;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reverse a confirmed payout that later failed/was returned (build plan P3-08, doc 03). The
 * correction is a NEW balanced transaction — DR platform_cash / CR provider_payable, the mirror of
 * the payout — never a delete of the original. It restores `provider_payable` to its pre-payout
 * value; both the original and the reversal remain in the append-only ledger.
 */
final class ReversePayout
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Outbox $outbox,
    ) {}

    public function handle(Payout $payout, string $reason): void
    {
        DB::transaction(function () use ($payout, $reason): void {
            $locked = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Succeeded || $locked->reversed_at !== null) {
                throw new InvalidArgumentException('Only a confirmed, not-yet-reversed payout can be reversed.');
            }

            $txn = $this->ledger->post(
                TxnKind::PayoutReversal,
                [
                    LedgerEntryInput::debit($this->ledger->account(AccountKind::PlatformCash, currency: $locked->currency), $locked->amount_minor),
                    LedgerEntryInput::credit($this->ledger->account(AccountKind::ProviderPayable, $locked->party_id, $locked->currency), $locked->amount_minor),
                ],
                referenceType: 'payout',
                referenceId: $locked->id,
                memo: "payout reversal: {$reason}",
            );

            $locked->update([
                'status' => PaymentStatus::Failed->value,
                'reversal_transaction_id' => $txn->id,
                'reversed_at' => now(),
                'failure_code' => 'reversed',
            ]);

            $this->outbox->publish('payout.reversed', ['payout_id' => $locked->id, 'reason' => $reason]);
        });
    }
}
