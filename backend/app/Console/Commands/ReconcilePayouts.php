<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Money\Actions\RequestPayout;
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

    /** How long a reservation may sit un-dispatched before the sweep sends it itself. */
    private const STRANDED_AFTER_MINUTES = 5;

    public function handle(ResolvePayout $resolve, RequestPayout $request): int
    {
        // First: reservations that were never handed to the gateway at all.
        //
        // `RequestPayout` commits the reservation before it calls out, so a timeout leaves a
        // `pending` row with no `external_ref` — funds correctly held, money correctly not sent.
        // `ResolvePayout` cannot move those on (it has no reference to ask about) and deliberately
        // skips them, so without this the only thing that would ever finish them is the client
        // happening to retry under the same Idempotency-Key. Nobody's payout should depend on that.
        $stranded = 0;
        Payout::query()
            ->where('status', PaymentStatus::Pending->value)
            ->whereNull('external_ref')
            ->where('requested_at', '<=', now()->subMinutes(self::STRANDED_AFTER_MINUTES))
            ->orderBy('requested_at')
            ->chunkById(100, function ($payouts) use ($request, &$stranded): void {
                foreach ($payouts as $payout) {
                    $request->dispatchReserved($payout);
                    $stranded++;
                }
            });

        // Then: everything already with the gateway, so a confirmed disbursement posts to the
        // ledger even if its webhook was lost.
        $count = 0;
        Payout::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->whereNotNull('external_ref')
            ->orderBy('requested_at')
            ->chunkById(100, function ($payouts) use ($resolve, &$count): void {
                foreach ($payouts as $payout) {
                    $resolve->handle($payout);
                    $count++;
                }
            });

        $this->info("Dispatched {$stranded} stranded reservation(s); reconciled {$count} pending payout(s).");

        return self::SUCCESS;
    }
}
