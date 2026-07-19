<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Gateways\GatewayStatus;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\PaymentPurpose;
use App\Domain\Money\PaymentStatus;
use App\Domain\Money\TxnKind;
use App\Models\LedgerTransaction;
use App\Models\PaymentIntent;
use App\Support\Outbox;

/**
 * Resolve a payment intent from an authoritative gateway status (build plan P3-05). Idempotent: a
 * already-resolved intent is left untouched, so a webhook and a poll racing for the same payment
 * produce exactly one ledger transaction. On success it posts the collection: the money is with the
 * aggregator, so it lands in `gateway_receivable` (settlement to `platform_cash` is reconciliation,
 * doc 03) against the relevant liability.
 *
 * MUST be called inside a transaction that holds a lock on the intent (see ProcessPaymentWebhook).
 */
final class ApplyGatewayResult
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Outbox $outbox,
    ) {}

    public function handle(PaymentIntent $intent, GatewayStatus $status): void
    {
        if ($intent->isResolved()) {
            return; // terminal already — nothing to do
        }

        match ($status) {
            GatewayStatus::Succeeded => $this->succeed($intent),
            GatewayStatus::Failed => $this->fail($intent, PaymentStatus::Failed),
            GatewayStatus::Expired => $this->fail($intent, PaymentStatus::Expired),
            GatewayStatus::Pending => null, // still waiting; no state change
        };
    }

    private function succeed(PaymentIntent $intent): void
    {
        $txn = $this->postCollection($intent);

        $intent->update([
            'status' => PaymentStatus::Succeeded->value,
            'resolved_at' => now(),
            'ledger_transaction_id' => $txn->id,
        ]);

        $this->outbox->publish('payment.succeeded', [
            'payment_intent_id' => $intent->id,
            'purpose' => $intent->purpose->value,
            'amount_minor' => $intent->amount_minor,
        ]);
    }

    private function fail(PaymentIntent $intent, PaymentStatus $status): void
    {
        $intent->update([
            'status' => $status->value,
            'resolved_at' => now(),
        ]);

        $this->outbox->publish('payment.failed', [
            'payment_intent_id' => $intent->id,
            'status' => $status->value,
        ]);
    }

    private function postCollection(PaymentIntent $intent): LedgerTransaction
    {
        $receivable = $this->ledger->account(AccountKind::GatewayReceivable, currency: $intent->currency);

        [$liability, $kind] = match ($intent->purpose) {
            PaymentPurpose::LeadCredits => [
                $this->ledger->account(AccountKind::LeadCreditLiability, $intent->party_id, $intent->currency),
                TxnKind::LeadCreditPurchase,
            ],
            PaymentPurpose::Escrow => [
                $this->ledger->account(AccountKind::EscrowLiability, currency: $intent->currency),
                TxnKind::EscrowCollection,
            ],
        };

        return $this->ledger->post(
            $kind,
            [
                LedgerEntryInput::debit($receivable, $intent->amount_minor),
                LedgerEntryInput::credit($liability, $intent->amount_minor),
            ],
            referenceType: $intent->purpose === PaymentPurpose::Escrow ? 'engagement' : 'payment_intent',
            referenceId: $intent->purpose === PaymentPurpose::Escrow ? $intent->engagement_id : $intent->id,
            memo: "collection {$intent->purpose->value}",
        );
    }
}
