<?php

declare(strict_types=1);

use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Domain\Money\UnbalancedTransaction;
use App\Models\LedgerTransaction;
use App\Models\Party;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * P3-01 acceptance (doc 03): the double-entry ledger. Postings are balanced; balances are computed
 * from entries; the ledger is append-only at the DATABASE level (UPDATE/DELETE raise) regardless of
 * the connecting role; and an unbalanced transaction is rejected by the DB.
 */
it('posts a balanced double-entry transaction and computes balances from entries', function () {
    $ledger = app(Ledger::class);
    $pro = Party::factory()->individual()->create();
    $cash = $ledger->account(AccountKind::PlatformCash);
    $liability = $ledger->account(AccountKind::LeadCreditLiability, $pro->id);

    // Lead-credit purchase: DR platform_cash / CR lead_credit_liability(pro).
    $txn = $ledger->post(TxnKind::LeadCreditPurchase, [
        LedgerEntryInput::debit($cash, 1_000_000),
        LedgerEntryInput::credit($liability, 1_000_000),
    ]);

    expect($txn->entries()->count())->toBe(2)
        ->and($cash->balanceMinor())->toBe(1_000_000)        // asset: up on debit
        ->and($liability->balanceMinor())->toBe(-1_000_000); // liability reads negative; owed = 1,000,000
});

it('rejects an unbalanced transaction before touching the DB', function () {
    $ledger = app(Ledger::class);
    $cash = $ledger->account(AccountKind::PlatformCash);
    $revenue = $ledger->account(AccountKind::PlatformRevenue);

    expect(fn () => $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($cash, 1000),
        LedgerEntryInput::credit($revenue, 900),
    ]))->toThrow(UnbalancedTransaction::class);
});

it('requires a party for party-scoped account kinds', function () {
    expect(fn () => app(Ledger::class)->account(AccountKind::ProviderPayable))
        ->toThrow(InvalidArgumentException::class);
});

it('resolves a single platform account per kind (idempotent)', function () {
    $ledger = app(Ledger::class);

    expect($ledger->account(AccountKind::PlatformCash)->id)
        ->toBe($ledger->account(AccountKind::PlatformCash)->id);
});

it('matches the ledger_balances view to the computed balance', function () {
    $ledger = app(Ledger::class);
    $cash = $ledger->account(AccountKind::PlatformCash);
    $revenue = $ledger->account(AccountKind::PlatformRevenue);
    $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($cash, 700),
        LedgerEntryInput::credit($revenue, 700),
    ]);

    $viewBalance = (int) DB::table('ledger_balances')->where('account_id', $cash->id)->value('balance_minor');

    expect($viewBalance)->toBe(700)->toBe($cash->balanceMinor());
});

it('forbids UPDATE on ledger_entries at the DB level (append-only)', function () {
    $ledger = app(Ledger::class);
    $txn = $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($ledger->account(AccountKind::PlatformCash), 500),
        LedgerEntryInput::credit($ledger->account(AccountKind::PlatformRevenue), 500),
    ]);
    $entryId = $txn->entries()->firstOrFail()->id;

    expect(fn () => DB::table('ledger_entries')->where('id', $entryId)->update(['amount_minor' => 999]))
        ->toThrow(QueryException::class);
});

it('forbids DELETE on ledger_entries at the DB level (append-only)', function () {
    $ledger = app(Ledger::class);
    $txn = $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($ledger->account(AccountKind::PlatformCash), 500),
        LedgerEntryInput::credit($ledger->account(AccountKind::PlatformRevenue), 500),
    ]);
    $entryId = $txn->entries()->firstOrFail()->id;

    expect(fn () => DB::table('ledger_entries')->where('id', $entryId)->delete())
        ->toThrow(QueryException::class);
});

it('forbids UPDATE on ledger_transactions at the DB level (append-only)', function () {
    $ledger = app(Ledger::class);
    $txn = $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($ledger->account(AccountKind::PlatformCash), 500),
        LedgerEntryInput::credit($ledger->account(AccountKind::PlatformRevenue), 500),
    ]);

    expect(fn () => DB::table('ledger_transactions')->where('id', $txn->id)->update(['memo' => 'tampered']))
        ->toThrow(QueryException::class);
});

it('rejects a non-positive entry amount at the DB (CHECK)', function () {
    $ledger = app(Ledger::class);
    $cash = $ledger->account(AccountKind::PlatformCash);
    $txn = LedgerTransaction::factory()->create();

    expect(fn () => DB::table('ledger_entries')->insert([
        'id' => (string) Str::uuid(),
        'transaction_id' => $txn->id,
        'account_id' => $cash->id,
        'direction' => 'debit',
        'amount_minor' => 0,
        'created_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects an unbalanced transaction at the DB (deferred balance constraint)', function () {
    $ledger = app(Ledger::class);
    $cash = $ledger->account(AccountKind::PlatformCash);
    $revenue = $ledger->account(AccountKind::PlatformRevenue);
    $txn = LedgerTransaction::factory()->create();

    // Insert legs that don't balance, then force the deferred check to run now.
    expect(function () use ($txn, $cash, $revenue) {
        DB::transaction(function () use ($txn, $cash, $revenue) {
            DB::table('ledger_entries')->insert([
                ['id' => (string) Str::uuid(), 'transaction_id' => $txn->id, 'account_id' => $cash->id, 'direction' => 'debit', 'amount_minor' => 1000, 'created_at' => now()],
                ['id' => (string) Str::uuid(), 'transaction_id' => $txn->id, 'account_id' => $revenue->id, 'direction' => 'credit', 'amount_minor' => 900, 'created_at' => now()],
            ]);
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    })->toThrow(QueryException::class);
});
