<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jobs (build plan P2-01, doc 02 as SUPERSEDED by doc 06 on geography). The unit of demand.
 *
 * `engagement_mode` makes geography optional: onsite/hybrid jobs MUST have an address; a remote job
 * has none. That rule is a DB CHECK (doc 06), not app validation — the database is the guarantee:
 *   CHECK (engagement_mode = 'remote' OR address_id IS NOT NULL)
 *
 * `assignment_mode` is how the job is matched (direct/dispatch/bidding); Phase 2 uses `direct`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE engagement_mode AS ENUM ('onsite','remote','hybrid')");
        DB::statement("CREATE TYPE assignment_mode AS ENUM ('direct','dispatch','bidding')");
        DB::statement("CREATE TYPE job_status AS ENUM (
            'draft','open','offered','engaged','scheduled','en_route','in_progress',
            'work_submitted','completed','cancelled','disputed','closed')");

        Schema::create('service_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_party_id')->constrained('parties');
            $table->foreignUuid('created_by_user_id')->constrained('users');
            $table->foreignUuid('skill_id')->constrained('skills');
            $table->foreignUuid('address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->text('title');
            $table->text('description')->nullable();
            $table->string('description_language')->nullable(); // fr | en (doc 09 user-text tagging)
            $table->bigInteger('budget_minor')->nullable();
            $table->char('currency', 3)->default('XAF');
            $table->smallInteger('urgency')->default(1);
            $table->boolean('requires_verified_provider')->default(false);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestampsTz();
        });

        // Native enums + citext reference + the conditional-address CHECK (added via raw SQL).
        DB::statement('ALTER TABLE service_jobs ADD COLUMN reference citext NOT NULL');
        DB::statement('ALTER TABLE service_jobs ADD CONSTRAINT jobs_reference_unique UNIQUE (reference)');
        DB::statement('ALTER TABLE service_jobs ADD COLUMN engagement_mode engagement_mode NOT NULL');
        DB::statement("ALTER TABLE service_jobs ADD COLUMN assignment_mode assignment_mode NOT NULL DEFAULT 'direct'");
        DB::statement("ALTER TABLE service_jobs ADD COLUMN price_model price_model NOT NULL DEFAULT 'quote_only'");
        DB::statement("ALTER TABLE service_jobs ADD COLUMN status job_status NOT NULL DEFAULT 'draft'");
        DB::statement('ALTER TABLE service_jobs ADD COLUMN scheduled_window tstzrange');

        // The guarantee: geography is required unless the work is remote (doc 06).
        DB::statement("ALTER TABLE service_jobs ADD CONSTRAINT jobs_address_required_unless_remote
            CHECK (engagement_mode = 'remote' OR address_id IS NOT NULL)");
        DB::statement('ALTER TABLE service_jobs ADD CONSTRAINT jobs_budget_check CHECK (budget_minor IS NULL OR budget_minor >= 0)');

        DB::statement('CREATE INDEX jobs_status_published_idx ON service_jobs (status, published_at DESC)');
        DB::statement('CREATE INDEX jobs_customer_created_idx ON service_jobs (customer_party_id, created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_jobs');
        DB::statement('DROP TYPE IF EXISTS job_status');
        DB::statement('DROP TYPE IF EXISTS assignment_mode');
        DB::statement('DROP TYPE IF EXISTS engagement_mode');
    }
};
