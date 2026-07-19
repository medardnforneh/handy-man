<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Gateways\PaymentGateway;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\PaymentStatus;
use App\Domain\Money\TxnKind;
use App\Models\Payout;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Resolve a pending payout against the gateway (build plan P3-08). On confirmed success it makes the
 * ledger posting — DR provider_payable / CR platform_cash (the money has now left our wallet) — and
 * links the transaction. On failure/expiry it just marks the payout terminal; nothing was posted, so
 * `provider_payable` is untouched. Idempotent and lock-guarded.
 */
final class ResolvePayout
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly Ledger $ledger,
        private readonly Outbox $outbox,
    ) {}

    public function handle(Payout $payout): void
    {
        DB::transaction(function () use ($payout): void {
            $locked = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();

            if ($locked->isResolved() || $locked->external_ref === null) {
                return;
            }

            $status = $this->gateway->fetchStatus($locked->external_ref)->status;

            if ($status === GatewayStatus::Succeeded) {
                $txn = $this->ledger->post(
                    TxnKind::Payout,
                    [
                        LedgerEntryInput::debit($this->ledger->account(AccountKind::ProviderPayable, $locked->party_id, $locked->currency), $locked->amount_minor),
                        LedgerEntryInput::credit($this->ledger->account(AccountKind::PlatformCash, currency: $locked->currency), $locked->amount_minor),
                    ],
                    referenceType: 'payout',
                    referenceId: $locked->id,
                    memo: 'payout to provider',
                );

                $locked->update([
                    'status' => PaymentStatus::Succeeded->value,
                    'resolved_at' => now(),
                    'ledger_transaction_id' => $txn->id,
                ]);

                $this->outbox->publish('payout.succeeded', ['payout_id' => $locked->id, 'amount_minor' => $locked->amount_minor]);

                return;
            }

            if ($status === GatewayStatus::Failed || $status === GatewayStatus::Expired) {
                $locked->update([
                    'status' => $status === GatewayStatus::Failed ? PaymentStatus::Failed->value : PaymentStatus::Expired->value,
                    'resolved_at' => now(),
                ]);

                $this->outbox->publish('payout.failed', ['payout_id' => $locked->id]);
            }
        });
    }
}
