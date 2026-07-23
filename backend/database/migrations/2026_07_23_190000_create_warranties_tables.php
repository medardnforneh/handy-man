<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Warranties + claims + remedy jobs (build plan P6-11, doc 06). Warranty is the strongest
 * anti-leakage mechanic there is — it exists only on-platform. A claim spawns a REAL remedy job with
 * a real engagement and assignment (free for the customer, unpaid for the provider), closing the loop
 * instead of leaving the fix to an email thread. The remedy engagement's origin is the warranty
 * claim — a third way into `engagements`, alongside an accepted offer and an accepted quotation.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE warranty_status AS ENUM ('active','claimed','expired','void')");

        Schema::create('warranties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('engagement_id')->unique()->constrained('engagements')->cascadeOnDelete();
            $table->integer('duration_days');
            $table->timestampTz('starts_at');
            $table->timestampTz('expires_at');
            $table->text('terms')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE warranties ADD COLUMN status warranty_status NOT NULL DEFAULT 'active'");
        DB::statement('ALTER TABLE warranties ADD CONSTRAINT warranties_duration_check CHECK (duration_days > 0)');
        DB::statement('ALTER TABLE warranties ADD CONSTRAINT warranties_window_check CHECK (expires_at > starts_at)');

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('warranty_id')->constrained('warranties');
            $table->foreignUuid('claimed_by_party_id')->constrained('parties');
            $table->text('description');
            $table->foreignUuid('remedy_job_id')->nullable()->constrained('service_jobs');
            $table->string('status')->default('open');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();
        });
        DB::statement("ALTER TABLE warranty_claims ADD CONSTRAINT warranty_claims_status_check
            CHECK (status IN ('open','remedied','rejected'))");

        // The warranty claim is a third origin for an engagement (the remedy engagement).
        Schema::table('engagements', function (Blueprint $table): void {
            $table->foreignUuid('warranty_claim_id')->nullable()->after('quotation_id')->constrained('warranty_claims');
        });
        DB::statement('ALTER TABLE engagements DROP CONSTRAINT engagements_origin_check');
        DB::statement('ALTER TABLE engagements ADD CONSTRAINT engagements_origin_check
            CHECK (offer_id IS NOT NULL OR quotation_id IS NOT NULL OR warranty_claim_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE engagements DROP CONSTRAINT engagements_origin_check');
        DB::statement('ALTER TABLE engagements ADD CONSTRAINT engagements_origin_check
            CHECK (offer_id IS NOT NULL OR quotation_id IS NOT NULL)');
        Schema::table('engagements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warranty_claim_id');
        });
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('warranties');
        DB::statement('DROP TYPE IF EXISTS warranty_status');
    }
};
