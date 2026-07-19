<?php

declare(strict_types=1);

use App\Domain\Money\AccountKind;
use App\Domain\Money\Actions\ResolveReconciliationException;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\LedgerTransaction;
use App\Models\OutboxMessage;
use App\Models\PaymentIntent;
use App\Models\ReconciliationException;
use App\Models\User;

/**
 * P3-09 acceptance (doc 03): the nightly job flags discrepancies as reconciliation exceptions and
 * alerts an admin — it never auto-corrects. A human resolves each with a balanced adjustment stamped
 * with their user id.
 */

/** Seed the ledger's platform_cash to a known value. */
function seedPlatformCash(int $amount): void
{
    $ledger = app(Ledger::class);
    $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($ledger->account(AccountKind::PlatformCash), $amount),
        LedgerEntryInput::credit($ledger->account(AccountKind::PlatformRevenue), $amount),
    ]);
}

it('raises a settlement_mismatch exception when the wallet disagrees with the ledger — without correcting', function () {
    seedPlatformCash(10_000);

    $this->artisan('reconcile:nightly', ['--wallet-cash' => 12_000])->assertSuccessful();

    $exception = ReconciliationException::where('kind', 'settlement_mismatch')->firstOrFail();
    expect($exception->status)->toBe(ReconciliationException::STATUS_OPEN)
        ->and($exception->amount_minor)->toBe(2_000) // wallet − ledger
        ->and(OutboxMessage::where('type', 'reconciliation.exception')->count())->toBe(1)
        // Never auto-corrected: the ledger is untouched.
        ->and(app(Ledger::class)->availableMinor(AccountKind::PlatformCash))->toBe(10_000);
});

it('raises no exception when the wallet matches the ledger', function () {
    seedPlatformCash(10_000);

    $this->artisan('reconcile:nightly', ['--wallet-cash' => 10_000])->assertSuccessful();

    expect(ReconciliationException::count())->toBe(0);
});

it('does not pile up duplicate open exceptions across nightly runs', function () {
    seedPlatformCash(10_000);

    $this->artisan('reconcile:nightly', ['--wallet-cash' => 12_000])->assertSuccessful();
    $this->artisan('reconcile:nightly', ['--wallet-cash' => 12_000])->assertSuccessful();

    expect(ReconciliationException::where('kind', 'settlement_mismatch')->where('status', 'open')->count())->toBe(1);
});

it('flags a succeeded intent that is missing its ledger transaction', function () {
    PaymentIntent::factory()->create(['status' => 'succeeded', 'ledger_transaction_id' => null]);

    $this->artisan('reconcile:nightly')->assertSuccessful();

    expect(ReconciliationException::where('kind', 'intent_missing_ledger')->count())->toBe(1);
});

it('resolves an exception with a balanced adjustment stamped by the admin', function () {
    $ledger = app(Ledger::class);
    $exception = ReconciliationException::factory()->create(['amount_minor' => 5_000]);
    $admin = User::factory()->create();

    app(ResolveReconciliationException::class)->handle($admin, $exception, [
        LedgerEntryInput::debit($ledger->account(AccountKind::PlatformCash), 5_000),
        LedgerEntryInput::credit($ledger->account(AccountKind::PlatformRevenue), 5_000),
    ], 'manual correction after checking the statement');

    $exception->refresh();
    expect($exception->status)->toBe(ReconciliationException::STATUS_RESOLVED)
        ->and($exception->resolved_by_user_id)->toBe($admin->id)
        ->and($exception->resolution_transaction_id)->not->toBeNull();

    $txn = LedgerTransaction::findOrFail($exception->resolution_transaction_id);
    expect($txn->created_by_user_id)->toBe($admin->id)
        ->and($txn->kind)->toBe(TxnKind::Adjustment);
});
