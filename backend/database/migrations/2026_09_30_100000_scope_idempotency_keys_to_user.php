<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scope the idempotency claim to the caller (CLAUDE.md rule #3).
 *
 * P0-06 made `idempotency_key` globally unique and left a note on the `user_id` column — "Scope for
 * later (auth arrives in P1)". P1 arrived; the scope never did. Two things followed from that:
 *
 *   1. The key was the WHOLE identity of a claim. A second caller presenting the same key, method,
 *      path and body was served the FIRST caller's stored response verbatim — someone else's job
 *      id, someone else's payload. Client keys are UUIDs, so this needed a deliberate collision,
 *      but the defence was the client's choice of key rather than anything the server enforced.
 *   2. Anyone could park a key another caller would later use, and that caller got a 422.
 *
 * `NULLS NOT DISTINCT` is what makes the claim still atomic for the unauthenticated mutating
 * endpoints — the three `/auth/*` routes, which carry a key and have no user. Without it Postgres
 * treats every NULL as distinct, every guest insert succeeds, and the replay protection those
 * routes rely on silently disappears. Same convention as `ledger_accounts`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropUnique('idempotency_keys_idempotency_key_unique');
        });

        DB::statement(
            'CREATE UNIQUE INDEX idempotency_keys_user_key_unique
             ON idempotency_keys (user_id, idempotency_key) NULLS NOT DISTINCT'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idempotency_keys_user_key_unique');

        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->unique('idempotency_key');
        });
    }
};
