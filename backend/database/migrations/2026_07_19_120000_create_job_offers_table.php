<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Job offers (build plan P2-05, doc 02). An offer connects a provider to a job. Two DB guarantees:
 *   - UNIQUE (job_id, provider_party_id): one live offer per provider per job;
 *   - PARTIAL UNIQUE (job_id) WHERE status='accepted': exactly ONE accepted offer per job. This is
 *     what makes AcceptOfferAction safe under concurrency (P2-06) — the DB, not hope.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE offer_origin AS ENUM ('customer_direct','system_dispatch','provider_bid')");
        DB::statement("CREATE TYPE offer_status AS ENUM ('pending','accepted','declined','withdrawn','expired','superseded')");

        Schema::create('job_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained('service_jobs')->cascadeOnDelete();
            $table->foreignUuid('provider_party_id')->constrained('parties');
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->default('XAF');
            $table->text('message')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['job_id', 'provider_party_id']); // one live offer per pro per job
        });

        DB::statement('ALTER TABLE job_offers ADD COLUMN origin offer_origin NOT NULL');
        DB::statement("ALTER TABLE job_offers ADD COLUMN status offer_status NOT NULL DEFAULT 'pending'");
        DB::statement('ALTER TABLE job_offers ADD CONSTRAINT job_offers_amount_check CHECK (amount_minor IS NULL OR amount_minor >= 0)');

        // Exactly one accepted offer per job — the concurrency backbone of P2-06.
        DB::statement("CREATE UNIQUE INDEX one_accepted_offer_per_job ON job_offers (job_id) WHERE status = 'accepted'");
        DB::statement('CREATE INDEX job_offers_provider_idx ON job_offers (provider_party_id, status, created_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
        DB::statement('DROP TYPE IF EXISTS offer_status');
        DB::statement('DROP TYPE IF EXISTS offer_origin');
    }
};
