<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The double-entry ledger (build plan P3-01, doc 03). This is the money source of truth: every
 * movement is a balanced transaction of two or more entries. Nothing about balances is stored — a
 * balance is a SUM (see the `ledger_balances` view).
 *
 * Invariants enforced by the DATABASE, not by app code:
 *   1. Append-only. Triggers reject any UPDATE/DELETE on entries and transactions (CLAUDE.md rule
 *      #1). We also REVOKE those grants for defence-in-depth, but the app connects as a superuser
 *      in dev where REVOKE is a no-op — the trigger is the real, role-independent guarantee.
 *   2. Every transaction balances (SUM debit == SUM credit) — a DEFERRABLE constraint trigger so a
 *      transaction and all its entries can be written inside one DB transaction.
 *   3. Entry amounts are strictly positive; direction carries the sign (doc 03 sign convention,
 *      documented in app/Support/Money.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE account_kind AS ENUM (
            'platform_cash','gateway_receivable','escrow_liability','provider_payable',
            'lead_credit_liability','promo_liability','platform_revenue'
        )");
        DB::statement("CREATE TYPE entry_direction AS ENUM ('debit','credit')");
        DB::statement("CREATE TYPE txn_kind AS ENUM (
            'lead_credit_purchase','lead_credit_spend','escrow_collection','gateway_settlement',
            'escrow_release','payout','payout_reversal','refund','referral_reward','referral_spend',
            'cash_settlement','adjustment'
        )");

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->nullable()->constrained('parties'); // NULL = platform-owned
            $table->char('currency', 3)->default('XAF');
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE ledger_accounts ADD COLUMN kind account_kind NOT NULL');
        // One account per (party, kind, currency); NULLS NOT DISTINCT so the single platform account
        // per kind is unique too (PG15+).
        DB::statement('CREATE UNIQUE INDEX ledger_accounts_identity
            ON ledger_accounts (party_id, kind, currency) NULLS NOT DISTINCT');

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_type')->nullable(); // 'engagement' | 'payment_intent' | 'payout' | ...
            $table->uuid('reference_id')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->text('memo')->nullable();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users'); // manual adjustments
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['reference_type', 'reference_id']);
        });
        DB::statement('ALTER TABLE ledger_transactions ADD COLUMN kind txn_kind NOT NULL');

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transaction_id')->constrained('ledger_transactions');
            $table->foreignUuid('account_id')->constrained('ledger_accounts');
            $table->bigInteger('amount_minor');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['account_id', 'created_at']);
        });
        DB::statement('ALTER TABLE ledger_entries ADD COLUMN direction entry_direction NOT NULL');
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_positive CHECK (amount_minor > 0)');

        // --- Invariant 1: append-only (role-independent). ---
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION forbid_ledger_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'ledger is append-only: % on % is forbidden', TG_OP, TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
        DB::statement('CREATE TRIGGER ledger_entries_append_only BEFORE UPDATE OR DELETE ON ledger_entries
            FOR EACH ROW EXECUTE FUNCTION forbid_ledger_mutation()');
        DB::statement('CREATE TRIGGER ledger_transactions_append_only BEFORE UPDATE OR DELETE ON ledger_transactions
            FOR EACH ROW EXECUTE FUNCTION forbid_ledger_mutation()');
        // Defence-in-depth for production (non-superuser app role); a no-op under a superuser.
        DB::statement('REVOKE UPDATE, DELETE ON ledger_entries FROM PUBLIC');
        DB::statement('REVOKE UPDATE, DELETE ON ledger_transactions FROM PUBLIC');

        // --- Invariant 2: every transaction balances (deferred to commit). ---
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_transaction_balances() RETURNS trigger AS $$
            DECLARE d bigint; c bigint;
            BEGIN
                SELECT
                    COALESCE(SUM(amount_minor) FILTER (WHERE direction = 'debit'), 0),
                    COALESCE(SUM(amount_minor) FILTER (WHERE direction = 'credit'), 0)
                INTO d, c
                FROM ledger_entries WHERE transaction_id = NEW.transaction_id;

                IF d <> c THEN
                    RAISE EXCEPTION 'Ledger transaction % unbalanced: debits=% credits=%', NEW.transaction_id, d, c;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
        DB::statement('CREATE CONSTRAINT TRIGGER ledger_must_balance
            AFTER INSERT ON ledger_entries
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION assert_transaction_balances()');

        // Balances are computed, never stored (doc 03). Debit-normal sign: debit +, credit -.
        DB::statement(<<<'SQL'
            CREATE VIEW ledger_balances AS
            SELECT account_id,
                   SUM(CASE WHEN direction = 'debit' THEN amount_minor ELSE -amount_minor END) AS balance_minor
            FROM ledger_entries
            GROUP BY account_id
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS ledger_balances');
        DB::statement('DROP TRIGGER IF EXISTS ledger_must_balance ON ledger_entries');
        DB::statement('DROP TRIGGER IF EXISTS ledger_entries_append_only ON ledger_entries');
        DB::statement('DROP TRIGGER IF EXISTS ledger_transactions_append_only ON ledger_transactions');
        DB::statement('DROP FUNCTION IF EXISTS assert_transaction_balances()');
        DB::statement('DROP FUNCTION IF EXISTS forbid_ledger_mutation()');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
        DB::statement('DROP TYPE IF EXISTS txn_kind');
        DB::statement('DROP TYPE IF EXISTS entry_direction');
        DB::statement('DROP TYPE IF EXISTS account_kind');
    }
};
