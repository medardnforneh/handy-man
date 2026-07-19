<?php

declare(strict_types=1);

namespace App\Domain\Money\Actions;

use App\Domain\Engagements\MilestoneStatus;
use App\Domain\Money\AccountKind;
use App\Domain\Money\InsufficientEscrow;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\Engagement;
use App\Models\Milestone;
use App\Support\Money;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Approve a milestone and release its slice from escrow (build plan P3-10/14, doc 03/06). Each
 * approval releases only that milestone's amount — DR escrow_liability / CR provider_payable (net) /
 * CR platform_revenue (commission) — so a partial approval leaves the remainder escrowed. Idempotent
 * (an already-paid milestone is a no-op) and serialised per engagement via an advisory lock, so the
 * escrow balance can't be over-released under concurrency.
 */
final class ApproveMilestone
{
    public function __construct(
        private readonly Ledger $ledger,
        private readonly Outbox $outbox,
    ) {}

    public function handle(Milestone $milestone): void
    {
        DB::transaction(function () use ($milestone): void {
            $engagement = Engagement::query()->whereKey($milestone->engagement_id)->firstOrFail();

            // Serialise all money movement for this engagement.
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', [$engagement->id]);

            $locked = Milestone::query()->whereKey($milestone->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [MilestoneStatus::Approved, MilestoneStatus::Paid], true)) {
                return; // already released
            }

            $amount = $locked->amount_minor;

            if ($amount > 0) {
                $held = $this->ledger->escrowHeldMinor($engagement->id, $engagement->currency);
                if ($held < $amount) {
                    throw new InsufficientEscrow;
                }

                $fee = Money::of($amount, $engagement->currency)->percentage((int) config('money.commission_bps'))->minor;
                $providerNet = $amount - $fee;

                $entries = [
                    LedgerEntryInput::debit($this->ledger->account(AccountKind::EscrowLiability, currency: $engagement->currency), $amount),
                ];
                if ($providerNet > 0) {
                    $entries[] = LedgerEntryInput::credit($this->ledger->account(AccountKind::ProviderPayable, $engagement->provider_party_id, $engagement->currency), $providerNet);
                }
                if ($fee > 0) {
                    $entries[] = LedgerEntryInput::credit($this->ledger->account(AccountKind::PlatformRevenue, currency: $engagement->currency), $fee);
                }

                $this->ledger->post(
                    TxnKind::EscrowRelease,
                    $entries,
                    referenceType: 'engagement',
                    referenceId: $engagement->id,
                    memo: "milestone {$locked->position} release",
                );
            }

            $locked->update(['status' => MilestoneStatus::Paid->value, 'approved_at' => now()]);

            $this->outbox->publish('milestone.approved', [
                'milestone_id' => $locked->id,
                'engagement_id' => $engagement->id,
                'amount_minor' => $amount,
            ]);
        });
    }
}
