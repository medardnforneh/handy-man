<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Engagements + assignments (build plan P2-06/P2-07, doc 02/06).
 *
 * `engagements.job_id` is UNIQUE — exactly ONE engagement per job. Together with the accepted-offer
 * partial unique index and a lockForUpdate on the job, this makes AcceptOfferAction safe under
 * concurrency: 20 parallel accepts resolve to exactly one engagement.
 *
 * Assignments are how work is delegated. An individual provider auto-gets one `lead` assignment
 * (created in AcceptOfferAction); a company's dispatcher creates them. The app only ever queries
 * `assignments` — it never branches on individual-vs-company (doc 02 uniform-assignment rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE assignment_role AS ENUM ('lead','helper')");
        DB::statement("CREATE TYPE assignment_status AS ENUM ('assigned','accepted','declined','en_route','on_site','completed','removed')");

        Schema::create('engagements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->unique()->constrained('service_jobs'); // one engagement per job
            $table->foreignUuid('provider_party_id')->constrained('parties');
            $table->foreignUuid('offer_id')->constrained('job_offers');
            $table->bigInteger('agreed_amount_minor');
            $table->char('currency', 3)->default('XAF');
            $table->bigInteger('platform_fee_minor')->default(0);
            $table->boolean('is_escrowed')->default(false);
            $table->timestampTz('accepted_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE engagements ADD CONSTRAINT engagements_amount_check CHECK (agreed_amount_minor >= 0)');

        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignUuid('worker_user_id')->constrained('users');
            $table->foreignUuid('assigned_by_user_id')->constrained('users');
            $table->timestampTz('assigned_at')->useCurrent();
            $table->timestampsTz();

            $table->unique(['engagement_id', 'worker_user_id']);
        });
        DB::statement("ALTER TABLE assignments ADD COLUMN role assignment_role NOT NULL DEFAULT 'lead'");
        DB::statement("ALTER TABLE assignments ADD COLUMN status assignment_status NOT NULL DEFAULT 'assigned'");

        // At most one active lead per engagement.
        DB::statement("CREATE UNIQUE INDEX one_lead_per_engagement ON assignments (engagement_id)
            WHERE role = 'lead' AND status <> 'removed'");
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('engagements');
        DB::statement('DROP TYPE IF EXISTS assignment_status');
        DB::statement('DROP TYPE IF EXISTS assignment_role');
    }
};
