<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site visits (build plan P2.5-04, doc 06). Many providers won't quote without seeing the job; a
 * chargeable-but-creditable visit filters tyre-kickers — the fee is credited against the final quote
 * when it's accepted. Onsite/hybrid are physical visits; remote is a video consultation (same row).
 *
 * The credit is realised at quote acceptance: the resulting engagement's agreed amount is reduced by
 * the completed chargeable visit fee, recorded on `engagements.visit_credit_minor` for transparency.
 * (Actual fee movement lands with the ledger in Phase 3; here it shapes the engagement value.)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE site_visit_status AS ENUM ('scheduled','completed','cancelled','no_show')");

        Schema::create('site_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained('service_jobs');
            $table->foreignUuid('provider_party_id')->constrained('parties');
            $table->timestampTz('scheduled_for');
            $table->boolean('is_chargeable')->default(false);
            $table->bigInteger('fee_minor')->default(0);
            $table->char('currency', 3)->default('XAF');
            $table->timestampTz('completed_at')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->foreignUuid('resulting_quotation_id')->nullable()->constrained('quotations');
            $table->timestampsTz();
        });
        DB::statement("ALTER TABLE site_visits ADD COLUMN status site_visit_status NOT NULL DEFAULT 'scheduled'");
        DB::statement('ALTER TABLE site_visits ADD CONSTRAINT site_visits_fee_check CHECK (fee_minor >= 0)');
        // A free visit can't carry a fee; a chargeable one should.
        DB::statement('ALTER TABLE site_visits ADD CONSTRAINT site_visits_chargeable_fee_check
            CHECK ((is_chargeable AND fee_minor > 0) OR (NOT is_chargeable AND fee_minor = 0))');

        // The credit applied to an engagement from creditable, completed site visits.
        Schema::table('engagements', function (Blueprint $table): void {
            $table->bigInteger('visit_credit_minor')->default(0)->after('agreed_amount_minor');
        });
        DB::statement('ALTER TABLE engagements ADD CONSTRAINT engagements_visit_credit_check CHECK (visit_credit_minor >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE engagements DROP CONSTRAINT IF EXISTS engagements_visit_credit_check');
        Schema::table('engagements', function (Blueprint $table): void {
            $table->dropColumn('visit_credit_minor');
        });
        Schema::dropIfExists('site_visits');
        DB::statement('DROP TYPE IF EXISTS site_visit_status');
    }
};
