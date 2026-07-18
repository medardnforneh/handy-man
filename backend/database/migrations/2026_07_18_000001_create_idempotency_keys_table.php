<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency store (build plan P0-06, CLAUDE.md rule #3).
 *
 * Mobile networks here retry; duplicate writes are the default failure, not the edge case. A
 * mutating request carries an `Idempotency-Key`; the first request for a key is executed and its
 * response stored; any replay of the same key returns the stored response WITHOUT re-executing.
 *
 * The unique index on `idempotency_key` is the concurrency lock: two parallel requests race to
 * INSERT the same key and exactly one wins (the loser replays or 409s).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Client-supplied key (a UUID). Unique = the atomic claim.
            $table->string('idempotency_key');

            // Scope for later (auth arrives in P1). Nullable until then.
            $table->unsignedBigInteger('user_id')->nullable();

            // Request fingerprint — a replayed key MUST be the same request, else it's a misuse.
            $table->string('request_method', 10);
            $table->string('request_path', 2048);
            $table->char('request_hash', 64); // sha256 hex of method|path|body

            // Captured response (null until the first execution completes).
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_headers')->nullable();
            $table->text('response_body')->nullable();

            $table->string('status', 20)->default('processing');

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at');

            $table->unique('idempotency_key');
            $table->index('expires_at');
        });

        // Guard the state values at the DB, not just in app code (definition of done).
        DB::statement("ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_status_check
            CHECK (status IN ('processing', 'completed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
