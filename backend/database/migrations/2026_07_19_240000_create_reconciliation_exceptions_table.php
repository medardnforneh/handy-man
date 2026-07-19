<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliation exceptions (build plan P3-09, doc 03). The nightly job never auto-corrects a
 * discrepancy — it records it here and alerts an admin. A human then decides, and the correction is a
 * balanced adjustment transaction with `created_by_user_id` set (linked via `resolution_transaction_id`).
 *
 * "The day you cannot answer 'does our ledger match the bank?' in under a minute is the day the
 * platform stops being trustworthy."
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE reconciliation_status AS ENUM ('open','resolved')");

        Schema::create('reconciliation_exceptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind'); // 'settlement_mismatch' | 'intent_missing_ledger' | ...
            $table->text('detail');
            $table->bigInteger('amount_minor')->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->timestampTz('detected_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignUuid('resolved_by_user_id')->nullable()->constrained('users');
            $table->foreignUuid('resolution_transaction_id')->nullable()->constrained('ledger_transactions');
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE reconciliation_exceptions ADD COLUMN status reconciliation_status NOT NULL DEFAULT 'open'");

        // At most one OPEN exception per (kind, reference) so nightly re-runs don't pile up duplicates.
        // NULLS NOT DISTINCT so reference-less kinds (e.g. settlement_mismatch) are unique per kind too.
        DB::statement("CREATE UNIQUE INDEX one_open_exception_per_ref
            ON reconciliation_exceptions (kind, reference_type, reference_id) NULLS NOT DISTINCT
            WHERE status = 'open'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_exceptions');
        DB::statement('DROP TYPE IF EXISTS reconciliation_status');
    }
};
