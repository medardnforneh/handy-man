<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give `parties.data_key` something to protect, and give erasure somewhere to record what it
 * destroyed.
 *
 * P1-10 mints a 256-bit key per party, encrypts it at rest, and destroys it on erasure — and
 * nothing was ever encrypted with it. Grep found two uses: minting, and nulling. So
 * "crypto-shred erasure", which doc 04 and the P1-10 entry both present as the thing that resolves
 * erasure-versus-an-append-only-ledger, was ceremonial: the erasure guarantee rested entirely on
 * the explicit row deletes beside it, and `ErasureTest` asserted the key was null rather than that
 * anything had become unrecoverable.
 *
 * Two columns:
 *
 *   `verification_documents.encryption_scheme` — identity papers were encrypted with `Crypt`, i.e.
 *   the application key, which every party shares and which erasure cannot destroy. New documents
 *   are encrypted with the owning party's own key, so destroying that key really does make them
 *   unreadable. Existing rows stay readable under the old scheme; NULL means `app_key` and is
 *   treated as such, because a migration cannot re-encrypt what it cannot decrypt safely in bulk.
 *
 *   `media.purged_at` — the same audit shape `verification_documents` already has. Erasure now
 *   destroys the bytes of media the party owns (their voice notes, their premises), and the row
 *   survives to say so: a thread that referenced a voice note should show that something was
 *   there and is gone, not silently lose the reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_documents', function (Blueprint $table): void {
            $table->string('encryption_scheme', 16)->nullable()->after('storage_path');
        });

        DB::statement("ALTER TABLE verification_documents
            ADD CONSTRAINT verification_documents_encryption_scheme_check
            CHECK (encryption_scheme IS NULL OR encryption_scheme IN ('app_key', 'party_key'))");

        // Say it out loud for the rows that already exist, rather than leaving the meaning of NULL
        // to be rediscovered later.
        DB::statement("UPDATE verification_documents SET encryption_scheme = 'app_key' WHERE encryption_scheme IS NULL");

        Schema::table('media', function (Blueprint $table): void {
            $table->timestampTz('purged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('verification_documents', function (Blueprint $table): void {
            $table->dropColumn('encryption_scheme');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn('purged_at');
        });
    }
};
