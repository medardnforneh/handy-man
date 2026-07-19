<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Money\Actions\ReconcilePaymentIntent;
use App\Domain\Money\PaymentStatus;
use App\Models\PaymentIntent;
use Illuminate\Console\Command;

/**
 * Reconciliation poller (build plan P3-06). Sweeps every unresolved payment intent and asks the
 * gateway for the authoritative status, so a lost webhook still resolves and a stuck intent past its
 * expiry is force-expired. Scheduled on a backoff cadence (10s, 30s, 1m, 2m, 5m…); the sweep itself
 * is cheap and idempotent, so re-running is always safe.
 */
final class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile';

    protected $description = 'Poll the gateway to resolve pending payment intents and expire stuck ones';

    public function handle(ReconcilePaymentIntent $reconcile): int
    {
        $count = 0;

        PaymentIntent::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->orderBy('initiated_at')
            ->chunkById(100, function ($intents) use ($reconcile, &$count): void {
                foreach ($intents as $intent) {
                    $reconcile->handle($intent);
                    $count++;
                }
            });

        $this->info("Reconciled {$count} pending intent(s).");

        return self::SUCCESS;
    }
}
