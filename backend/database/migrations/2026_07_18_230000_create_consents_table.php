<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consents (build plan P1-05, doc 04 §4 — Law No. 2024/017). APPEND-ONLY log: every grant and
 * revoke is a new row, so the full audit trail survives. Current state = the latest row per
 * (user, purpose). Records `policy_version` and, crucially, the `presented_locale` — a consent
 * captured against French text shown to an English-operating user is not "informed".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose');
            $table->boolean('granted'); // true = grant, false = revoke
            $table->string('policy_version');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'purpose', 'created_at']);
        });

        DB::statement("ALTER TABLE consents ADD COLUMN presented_locale text NOT NULL DEFAULT 'fr'");
        DB::statement("ALTER TABLE consents ADD CONSTRAINT consents_presented_locale_check
            CHECK (presented_locale IN ('fr','en'))");
        DB::statement("ALTER TABLE consents ADD CONSTRAINT consents_purpose_check
            CHECK (purpose IN ('terms','privacy','location_tracking','id_verification','marketing'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
