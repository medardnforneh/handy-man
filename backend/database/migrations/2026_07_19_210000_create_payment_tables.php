<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment intents and events (build plan P3-04/05, doc 03).
 *
 * A MoMo collection is a long-lived, USSD-driven affair: `pending` is a normal state, webhooks get
 * lost or duplicated, and a poll may resolve it instead. So:
 *   - `payment_intents.idempotency_key` is UNIQUE — re-initiating with the same key returns the same
 *     intent, never a second charge.
 *   - `(gateway, external_ref)` is unique so a gateway reference maps to exactly one intent.
 *   - `payment_events` has UNIQUE (gateway, external_ref, event_type) — THE duplicate-webhook
 *     defence. Insert the event first; a conflict means we've already seen it → 200 and stop
 *     (P3-05: 10 duplicate webhooks → 1 ledger transaction).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE payment_status AS ENUM ('pending','processing','succeeded','failed','expired')");
        DB::statement("CREATE TYPE payment_purpose AS ENUM ('escrow','lead_credits')");

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained('parties');
            $table->foreignUuid('engagement_id')->nullable()->constrained('engagements');
            $table->string('gateway');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('XAF');
            $table->string('external_ref')->nullable();
            $table->string('idempotency_key')->unique();
            $table->foreignUuid('ledger_transaction_id')->nullable()->constrained('ledger_transactions');
            $table->timestampTz('initiated_at')->useCurrent();
            $table->timestampTz('expires_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->jsonb('raw')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE payment_intents ADD COLUMN msisdn citext NOT NULL');
        DB::statement('ALTER TABLE payment_intents ADD COLUMN purpose payment_purpose NOT NULL');
        DB::statement("ALTER TABLE payment_intents ADD COLUMN status payment_status NOT NULL DEFAULT 'pending'");
        DB::statement('ALTER TABLE payment_intents ADD CONSTRAINT payment_intents_amount_check CHECK (amount_minor > 0)');
        DB::statement('CREATE UNIQUE INDEX payment_intents_gateway_ref ON payment_intents (gateway, external_ref) WHERE external_ref IS NOT NULL');

        Schema::create('payment_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('gateway');
            $table->string('external_ref');
            $table->string('event_type');
            $table->boolean('signature_valid');
            $table->jsonb('payload');
            $table->timestampTz('received_at')->useCurrent();
            $table->timestampTz('processed_at')->nullable();

            // The duplicate-webhook defence.
            $table->unique(['gateway', 'external_ref', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_intents');
        DB::statement('DROP TYPE IF EXISTS payment_purpose');
        DB::statement('DROP TYPE IF EXISTS payment_status');
    }
};
