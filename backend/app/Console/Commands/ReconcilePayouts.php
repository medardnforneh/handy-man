<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Money\Actions\ResolvePayout;
use App\Domain\Money\PaymentStatus;
use App\Models\Payout;
use Illuminate\Console\Command;

/**
 * Reconciliation poller for payouts (build plan P3-08), mirroring payments:reconcile. Asks the
 * gateway for the authoritative status of every unresolved payout so a confirmed disbursement posts
 * to the ledger even if its webhook was lost.
 */
final class ReconcilePayouts extends Command
{
    protected $signature = 'payouts:reconcile';

    protected $description = 'Poll the gateway to resolve pending payouts';

    public function handle(ResolvePayout $resolve): int
    {
        $count = 0;

        Payout::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->orderBy('requested_at')
            ->chunkById(100, function ($payouts) use ($resolve, &$count): void {
                foreach ($payouts as $payout) {
                    $resolve->handle($payout);
                    $count++;
                }
            });

        $this->info("Reconciled {$count} pending payout(s).");

        return self::SUCCESS;
    }
}
