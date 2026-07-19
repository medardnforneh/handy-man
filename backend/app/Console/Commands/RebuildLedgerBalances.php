<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the cached account balances from the ledger entries (build plan P3-02). The entries are
 * the source of truth; this recomputes the derived `ledger_balances_cached` materialized view so the
 * cache can never silently drift from the truth. Runs on a schedule and after reconciliation.
 */
final class RebuildLedgerBalances extends Command
{
    protected $signature = 'ledger:rebuild-balances';

    protected $description = 'Recompute the cached ledger balances from the entries';

    public function handle(): int
    {
        DB::statement('REFRESH MATERIALIZED VIEW ledger_balances_cached');

        $this->info('Ledger balances rebuilt from entries.');

        return self::SUCCESS;
    }
}
