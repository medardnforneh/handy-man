<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotating refresh tokens (build plan P1-03, doc 02/04). The refresh token is an opaque 256-bit
 * secret; only its hash is stored. Tokens rotate on every use within a `family_id`; presenting an
 * already-rotated (revoked) token means it was stolen/replayed, so the WHOLE family is revoked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('family_id'); // rotation family — reuse detection revokes it wholesale
            $table->string('token_hash', 64)->unique(); // sha256 hex; never store the raw token
            $table->uuid('device_id')->nullable(); // FK added with devices in P1-04
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->uuid('replaced_by_id')->nullable(); // the token this one rotated into
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'family_id']);
            $table->index('family_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
