<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cash settlements (build plan P3-15, doc 03/05). Cash is a first-class rail here, not a rounding
 * error: a provider can honestly record a job settled in cash off-platform. The platform never held
 * the money, but still earns its commission — so the settlement books commission as revenue and as a
 * debt the provider owes (a new `provider_receivable` asset account).
 *
 * Recording cash must be strictly better for the provider than hiding it: it builds their
 * on-platform history, completion rate and warranty coverage. This table is that record.
 */
return new class extends Migration
{
    // ALTER TYPE ... ADD VALUE cannot run inside a transaction block.
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TYPE account_kind ADD VALUE IF NOT EXISTS 'provider_receivable'");

        Schema::create('cash_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('engagement_id')->constrained('engagements');
            $table->foreignUuid('milestone_id')->nullable()->constrained('milestones');
            $table->foreignUuid('party_id')->constrained('parties'); // the provider who received cash
            $table->foreignUuid('recorded_by_user_id')->constrained('users');
            $table->bigInteger('amount_minor');
            $table->bigInteger('commission_minor');
            $table->char('currency', 3)->default('XAF');
            $table->foreignUuid('ledger_transaction_id')->nullable()->constrained('ledger_transactions');
            $table->timestampTz('recorded_at')->useCurrent();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE cash_settlements ADD CONSTRAINT cash_settlements_amount_check CHECK (amount_minor > 0)');
        DB::statement('ALTER TABLE cash_settlements ADD CONSTRAINT cash_settlements_commission_check CHECK (commission_minor >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_settlements');
        // The enum value is left in place (Postgres can't drop an enum value).
    }
};
