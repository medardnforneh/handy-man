<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A rebuildable cache of account balances (build plan P3-02, doc 03). The entries are the truth; this
 * is a derived read model — a MATERIALIZED VIEW computed from `ledger_entries`, refreshed by
 * `php artisan ledger:rebuild-balances`. Because it is derived, it can always be rebuilt from scratch
 * and must equal the live `ledger_balances` view (the P3-02 acceptance).
 *
 * We materialise now for one reason: to have the rebuild machinery and its equality test in place
 * before balances are read on hot paths (escrow release, payout eligibility). It stays a no-cost
 * view until something refreshes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW ledger_balances_cached AS
            SELECT account_id,
                   SUM(CASE WHEN direction = 'debit' THEN amount_minor ELSE -amount_minor END) AS balance_minor
            FROM ledger_entries
            GROUP BY account_id
            WITH NO DATA
        SQL);

        // A unique index lets us REFRESH ... CONCURRENTLY later without locking readers.
        DB::statement('CREATE UNIQUE INDEX ledger_balances_cached_account ON ledger_balances_cached (account_id)');
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS ledger_balances_cached');
    }
};
