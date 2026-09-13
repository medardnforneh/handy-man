<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a verification document's BYTES were destroyed (doc 04 retention: purged N days after a
 * rejection, and on erasure). The row stays — who reviewed what, when, and the plaintext's sha256
 * so a re-upload of the same paper is still detectable — but the object is gone and this says so,
 * so retention does not re-scan it and the admin can show "purged" instead of a 404.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_documents', function (Blueprint $table): void {
            $table->timestampTz('purged_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('verification_documents', function (Blueprint $table): void {
            $table->dropColumn('purged_at');
        });
    }
};
