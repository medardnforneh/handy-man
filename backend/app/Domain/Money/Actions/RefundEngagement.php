<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\Engagement;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Refund the escrow still held for an engagement (build plan P3-10, doc 03) — a dispute resolved for
 * the customer, or a cancellation. DR escrow_liability / CR platform_cash for whatever remains held
 * (already-released milestones are not clawed back here). Serialised per engagement; a no-op when no
 * escrow remains.
 */
final class RefundEngagement
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Outbox $outbox,
    ) {}

    public function handle(Engagement $engagement, string $reason): void
    {
        DB::transaction(function () use ($engagement, $reason): void {
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', [$engagement->id]);

            $held = $this->ledger->escrowHeldMinor($engagement->id, $engagement->currency);
            if ($held <= 0) {
                return;
            }

            $this->ledger->post(
                TxnKind::Refund,
                [
                    LedgerEntryInput::debit($this->ledger->account(AccountKind::EscrowLiability, currency: $engagement->currency), $held),
                    LedgerEntryInput::credit($this->ledger->account(AccountKind::PlatformCash, currency: $engagement->currency), $held),
                ],
                referenceType: 'engagement',
                referenceId: $engagement->id,
                memo: "refund: {$reason}",
            );

            $this->outbox->publish('engagement.refunded', [
                'engagement_id' => $engagement->id,
                'amount_minor' => $held,
            ]);
        });
    }
}
