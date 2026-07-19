<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos attached to a job at posting time (build plan P2-03). Stores the S3 object path only — the
 * upload flow (presigned direct-to-S3, EXIF stripped server-side) lands in P4/P5; here we just hold
 * the reference so the job can carry "here's what's broken" images.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained('service_jobs')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('position')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->index('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_photos');
    }
};
