<?php

declare(strict_types=1);

use App\Domain\Money\AccountKind;
use App\Domain\Money\Ledger;
use App\Domain\Money\LedgerEntryInput;
use App\Domain\Money\TxnKind;
use App\Models\LedgerAccount;
use App\Models\Party;
use Illuminate\Support\Facades\DB;

/**
 * P3-02 acceptance (doc 03): the cached balance equals the recomputed balance. The cache
 * (`ledger_balances_cached`) is a derived read model rebuilt from the entries — it must always match
 * the live `ledger_balances` view and the per-account computed balance.
 */

/**
 * @param  LedgerAccount  ...$accounts
 */
function assertCacheMatchesTruth(...$accounts): void
{
    foreach ($accounts as $account) {
        $cached = (int) DB::table('ledger_balances_cached')->where('account_id', $account->id)->value('balance_minor');
        $live = (int) DB::table('ledger_balances')->where('account_id', $account->id)->value('balance_minor');

        expect($cached)->toBe($live)->toBe($account->balanceMinor());
    }
}

it('rebuilds the cached balances to equal the recomputed balances', function () {
    $ledger = app(Ledger::class);
    $pro = Party::factory()->individual()->create();
    $cash = $ledger->account(AccountKind::PlatformCash);
    $revenue = $ledger->account(AccountKind::PlatformRevenue);
    $payable = $ledger->account(AccountKind::ProviderPayable, $pro->id);

    $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($cash, 20_000),
        LedgerEntryInput::credit($revenue, 20_000),
    ]);
    $ledger->post(TxnKind::Payout, [
        LedgerEntryInput::debit($payable, 5_000),
        LedgerEntryInput::credit($cash, 5_000),
    ]);

    $this->artisan('ledger:rebuild-balances')->assertSuccessful();
    assertCacheMatchesTruth($cash, $revenue, $payable);

    // Cash: +20,000 (debit) then −5,000 (credit) = 15,000.
    expect($cash->balanceMinor())->toBe(15_000);
});

it('keeps the cache equal to the truth after further postings and a rebuild', function () {
    $ledger = app(Ledger::class);
    $cash = $ledger->account(AccountKind::PlatformCash);
    $revenue = $ledger->account(AccountKind::PlatformRevenue);

    $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($cash, 1_000),
        LedgerEntryInput::credit($revenue, 1_000),
    ]);
    $this->artisan('ledger:rebuild-balances')->assertSuccessful();
    assertCacheMatchesTruth($cash, $revenue);

    // More movement, then rebuild from scratch again.
    $ledger->post(TxnKind::Adjustment, [
        LedgerEntryInput::debit($cash, 2_500),
        LedgerEntryInput::credit($revenue, 2_500),
    ]);
    $this->artisan('ledger:rebuild-balances')->assertSuccessful();
    assertCacheMatchesTruth($cash, $revenue);

    expect($cash->balanceMinor())->toBe(3_500);
});
