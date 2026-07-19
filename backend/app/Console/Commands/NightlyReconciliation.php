<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Money\AccountKind;
use App\Domain\Money\Actions\RaiseReconciliationException;
use App\Domain\Money\Ledger;
use App\Models\PaymentIntent;
use Illuminate\Console\Command;

/**
 * Nightly reconciliation (build plan P3-09, doc 03). Resolves stuck payments/payouts (via the
 * existing pollers), then runs integrity checks against the ledger. Any discrepancy is RECORDED as a
 * reconciliation_exception and an admin is alerted — never auto-corrected. A human resolves it with a
 * balanced adjustment.
 *
 * Pass --wallet-cash=<minor> with the aggregator's reported wallet balance to assert it matches the
 * ledger's platform_cash; a mismatch is the canonical settlement exception.
 */
final class NightlyReconciliation extends Command
{
    protected $signature = 'reconcile:nightly {--wallet-cash= : Reported wallet balance in minor units to check against ledger platform_cash}';

    protected $description = 'Resolve stuck payments/payouts and flag ledger discrepancies as reconciliation exceptions';

    public function handle(Ledger $ledger, RaiseReconciliationException $raise): int
    {
        // 1. Resolve anything stuck (lost webhooks, timeouts).
        $this->call('payments:reconcile');
        $this->call('payouts:reconcile');

        $raised = 0;

        // 2. Integrity: a succeeded intent must carry its ledger transaction.
        PaymentIntent::query()
            ->where('status', 'succeeded')
            ->whereNull('ledger_transaction_id')
            ->orderBy('id')
            ->chunkById(100, function ($intents) use ($raise, &$raised): void {
                foreach ($intents as $intent) {
                    $result = $raise->handle(
                        kind: 'intent_missing_ledger',
                        detail: "Succeeded payment intent {$intent->id} has no ledger transaction.",
                        amountMinor: $intent->amount_minor,
                        referenceType: 'payment_intent',
                        referenceId: $intent->id,
                    );
                    if ($result !== null) {
                        $raised++;
                    }
                }
            });

        // 3. Settlement: does the ledger's platform_cash match the actual wallet?
        $walletCash = $this->option('wallet-cash');
        if ($walletCash !== null) {
            $ledgerCash = $ledger->availableMinor(AccountKind::PlatformCash);
            $wallet = (int) $walletCash;
            if ($ledgerCash !== $wallet) {
                $diff = $wallet - $ledgerCash;
                $result = $raise->handle(
                    kind: 'settlement_mismatch',
                    detail: "Ledger platform_cash ({$ledgerCash}) != wallet ({$wallet}); difference {$diff}.",
                    amountMinor: $diff,
                );
                if ($result !== null) {
                    $raised++;
                }
            }
        }

        $this->info("Nightly reconciliation complete. {$raised} new exception(s) raised.");

        return self::SUCCESS;
    }
}
