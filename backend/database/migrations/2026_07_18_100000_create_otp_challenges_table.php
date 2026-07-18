<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OTP challenges (build plan P1-02, doc 02). Phone-first, OTP-first auth. Codes are stored HASHED,
 * never in plaintext. Rate limiting counts recent rows per phone (doc 04 §"OTP abuse").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('code_hash');
            $table->string('purpose'); // signup | login | phone_change
            $table->smallInteger('attempts')->default(0);
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at')->useCurrent();
        });

        // citext phone (matches users.phone_e164). Added via raw SQL — Blueprint has no citext helper.
        DB::statement('ALTER TABLE otp_challenges ADD COLUMN phone_e164 citext NOT NULL');

        // Rate-limit / lookup index: newest challenge per phone first.
        DB::statement('CREATE INDEX otp_challenges_phone_created_idx ON otp_challenges (phone_e164, created_at DESC)');

        DB::statement("ALTER TABLE otp_challenges ADD CONSTRAINT otp_challenges_purpose_check
            CHECK (purpose IN ('signup','login','phone_change'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
