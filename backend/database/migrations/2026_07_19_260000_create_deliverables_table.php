<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deliverables (build plan P4-08, doc 06). The remote path's proof of work: the provider submits an
 * artifact, the customer accepts or rejects it. Submission is narrated into the workspace thread
 * (`deliverable_submitted`) by the server, in the submit Action's transaction (rule #11).
 *
 * `media_url` holds the artifact reference for now; the `media` table (presigned S3, voice/photo)
 * arrives in P4-05 and will back a `media_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE deliverable_status AS ENUM ('pending','submitted','accepted','rejected')");

        Schema::create('deliverables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignUuid('milestone_id')->nullable()->constrained('milestones');
            $table->string('title');
            $table->text('media_url')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE deliverables ADD COLUMN status deliverable_status NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverables');
        DB::statement('DROP TYPE IF EXISTS deliverable_status');
    }
};
