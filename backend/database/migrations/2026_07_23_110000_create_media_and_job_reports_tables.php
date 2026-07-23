<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Media + job reports (build plan P5-04, doc 02/06). `media` is the polymorphic attachment table —
 * before/after photos on a job report, an id document, a voice note on a message. The stored file is
 * always EXIF-stripped; the location it carried is recorded server-side in `captured_point` instead,
 * so serving a customer's photo to a provider never leaks embedded GPS.
 *
 * `job_reports` is the on-site counterpart to the remote path's deliverables: what was done, the
 * materials used, any extra charges, and (later) a customer signature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_party_id')->constrained('parties');
            $table->string('attachable_type');   // 'job' | 'job_report' | 'verification_document' | 'message'
            $table->uuid('attachable_id');
            $table->string('kind');               // 'before' | 'after' | 'issue' | 'id_doc' | 'attachment'
            $table->text('storage_path');
            $table->char('sha256', 64);
            $table->bigInteger('bytes');
            $table->geography('captured_point', subtype: 'point', srid: 4326)->nullable();
            $table->timestampTz('captured_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['attachable_type', 'attachable_id']);
        });
        DB::statement('ALTER TABLE media ADD CONSTRAINT media_bytes_check CHECK (bytes > 0)');
        DB::statement("ALTER TABLE media ADD CONSTRAINT media_attachable_type_check
            CHECK (attachable_type IN ('job','job_report','verification_document','message'))");
        DB::statement("ALTER TABLE media ADD CONSTRAINT media_kind_check
            CHECK (kind IN ('before','after','issue','id_doc','attachment'))");

        Schema::create('job_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->text('summary');
            $table->jsonb('materials')->default('[]');   // [{label, qty, unit_cost_minor}]
            $table->bigInteger('extra_charges_minor')->default(0);
            $table->text('customer_signature_path')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE job_reports ADD CONSTRAINT job_reports_extra_charges_check CHECK (extra_charges_minor >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('job_reports');
        Schema::dropIfExists('media');
    }
};
