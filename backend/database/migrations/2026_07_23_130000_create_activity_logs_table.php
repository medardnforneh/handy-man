<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activity log (build plan P6-02, doc 04). The audit trail for sensitive human actions — most
 * importantly **every view of a verification document**, because the insider threat here is someone
 * browsing ID cards, and the only way that is ever detected is if reads are logged, not just edits.
 * Also carries admin adjudications later (P6-10, "attributable to a named admin").
 *
 * Append-only by convention (writes only). Records who (actor), what (action), on what (subject),
 * with context + source IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_user_id')->nullable()->constrained('users');
            $table->string('action');            // e.g. 'verification_document.viewed'
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });

        // An audit log is written, never rewritten — forbid UPDATE/DELETE at the DB (matches the
        // ledger's append-only discipline; the trigger is role-independent under the dev superuser).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION forbid_activity_log_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'activity_logs is append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER activity_logs_no_mutation
                BEFORE UPDATE OR DELETE ON activity_logs
                FOR EACH ROW EXECUTE FUNCTION forbid_activity_log_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS activity_logs_no_mutation ON activity_logs');
        Schema::dropIfExists('activity_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS forbid_activity_log_mutation()');
    }
};
