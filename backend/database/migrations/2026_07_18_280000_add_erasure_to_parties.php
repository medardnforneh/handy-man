<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crypto-shred erasure support (build plan P1-10, doc 04 §"erasure vs ledger conflict").
 *
 * Each party has a `data_key` — a per-party encryption key (encrypted at rest by the app key). PII
 * stored under this key (e.g. verification documents, P6) becomes unrecoverable the instant the key
 * is destroyed. Erasure = destroy the key + null plaintext identifiers + tombstone the party
 * (`erased_at`). The party ROW and its `id` survive, so append-only ledger FKs (Phase 3) stay
 * valid — the human simply becomes unidentifiable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->text('data_key')->nullable();      // per-party key (encrypted); null once erased
            $table->timestampTz('erased_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['data_key', 'erased_at']);
        });
    }
};
