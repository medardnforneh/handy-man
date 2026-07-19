<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payouts (build plan P3-08, doc 03). A disbursement to a provider's wallet. Idempotent on
 * `idempotency_key`. The ledger posting (DR provider_payable / CR platform_cash) is made only when
 * the gateway CONFIRMS success — a pending payout reserves funds via its row, not a ledger entry.
 *
 * A confirmed payout that later fails is corrected by a NEW balanced reversal transaction
 * (`reversal_transaction_id`), never by deleting the original — corrections are always new entries
 * (append-only ledger). The reversal restores `provider_payable` to its pre-payout value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained('parties');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('XAF');
            $table->string('gateway');
            $table->string('external_ref')->nullable();
            $table->string('idempotency_key')->unique();
            $table->foreignUuid('ledger_transaction_id')->nullable()->constrained('ledger_transactions');
            $table->foreignUuid('reversal_transaction_id')->nullable()->constrained('ledger_transactions');
            $table->timestampTz('requested_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->jsonb('raw')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE payouts ADD COLUMN msisdn citext NOT NULL');
        DB::statement("ALTER TABLE payouts ADD COLUMN status payment_status NOT NULL DEFAULT 'pending'");
        DB::statement('ALTER TABLE payouts ADD CONSTRAINT payouts_amount_check CHECK (amount_minor > 0)');
        DB::statement('CREATE UNIQUE INDEX payouts_gateway_ref ON payouts (gateway, external_ref) WHERE external_ref IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
