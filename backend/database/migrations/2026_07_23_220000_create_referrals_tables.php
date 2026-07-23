<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Referrals (build plan P8-01, doc 04). Codes, qualify-on-first-completed-paid-job, ledger-backed: a
 * referral reward is a real liability on the books (`promo_liability`), never a fictional number.
 * Self-referral and a duplicate referee are blocked — one referral per referee (UNIQUE), and the
 * referrer can't be the referee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->foreignUuid('party_id')->primary()->constrained('parties')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE referral_codes ADD COLUMN code citext NOT NULL');
        DB::statement('CREATE UNIQUE INDEX referral_codes_code_uidx ON referral_codes (code)');

        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_party_id')->constrained('parties');
            $table->foreignUuid('referee_party_id')->unique()->constrained('parties'); // one referral per referee
            $table->string('status')->default('pending');
            $table->foreignUuid('reward_transaction_id')->nullable()->constrained('ledger_transactions');
            $table->timestampTz('qualified_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE referrals ADD CONSTRAINT referrals_status_check
            CHECK (status IN ('pending','qualified','void'))");
        DB::statement('ALTER TABLE referrals ADD CONSTRAINT referrals_not_self_check
            CHECK (referrer_party_id <> referee_party_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('referral_codes');
    }
};
