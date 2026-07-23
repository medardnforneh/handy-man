<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reports + blocks (build plan P6-07, doc 02/04). A report is a complaint about a party (fed to the
 * admin queue). A block is a hard boundary: once a party blocks another, the two are never matched
 * again — and that must hold in **search, dispatch ranking, and offer creation**, in all three or it
 * isn't a block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_party_id')->constrained('parties');
            $table->foreignUuid('subject_party_id')->constrained('parties');
            $table->foreignUuid('job_id')->nullable()->constrained('service_jobs');
            $table->string('category');
            $table->text('body');
            $table->string('status')->default('open');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();

            $table->index(['subject_party_id', 'status']);
        });
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_category_check
            CHECK (category IN ('fraud','no_show','harassment','safety','spam','off_platform','other'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check
            CHECK (status IN ('open','reviewing','resolved','dismissed'))");
        DB::statement('ALTER TABLE reports ADD CONSTRAINT reports_not_self_check
            CHECK (reporter_party_id <> subject_party_id)');

        Schema::create('blocks', function (Blueprint $table) {
            $table->foreignUuid('party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignUuid('blocked_party_id')->constrained('parties')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['party_id', 'blocked_party_id']);
            $table->index('blocked_party_id');
        });
        DB::statement('ALTER TABLE blocks ADD CONSTRAINT blocks_not_self_check CHECK (party_id <> blocked_party_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('reports');
    }
};
