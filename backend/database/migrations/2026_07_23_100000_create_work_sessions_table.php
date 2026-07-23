<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Work sessions (build plan P5-03, doc 02). A worker checks in when they start on-site work and out
 * when they finish — geo + timestamp captured at each end. This is the physical-work counterpart to
 * the remote path's deliverables; it exists only for onsite/hybrid engagements (EngagementModePolicy
 * gates it, so a remote engagement never opens one).
 *
 * `start_point`/`end_point` are `geography(Point,4326)` recorded server-side; accuracy is the device
 * GPS accuracy in metres so the display can be honest about a soft fix. A worker may hold only one
 * open session per assignment at a time — the partial unique index is the hard guarantee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->timestampTz('started_at');
            $table->geography('start_point', subtype: 'point', srid: 4326)->nullable();
            $table->float('start_accuracy_m')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->geography('end_point', subtype: 'point', srid: 4326)->nullable();
            $table->float('end_accuracy_m')->nullable();
            $table->timestampsTz();

            $table->index('assignment_id');
        });

        // A session can't end before it started.
        DB::statement('ALTER TABLE work_sessions ADD CONSTRAINT work_sessions_span_check
            CHECK (ended_at IS NULL OR ended_at >= started_at)');

        // At most one open (not-yet-checked-out) session per assignment — you can't check in twice.
        DB::statement('CREATE UNIQUE INDEX one_open_session_per_assignment
            ON work_sessions (assignment_id) WHERE ended_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('work_sessions');
    }
};
