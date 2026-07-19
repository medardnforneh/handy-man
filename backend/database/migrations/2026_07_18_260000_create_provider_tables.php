<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provider profiles, service areas and listed skills (build plan P1-08, doc 02). You "become" a
 * provider by using the provider section (doc 10) — creating a profile is always allowed; a profile
 * is the thing the `has_provider_profile` fact is derived from, and a listed skill the `skill_listed`
 * fact. `verification_tier` feeds `identity_verified`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE price_model AS ENUM ('hourly','fixed','quote_only')");

        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->unique()->constrained('parties')->cascadeOnDelete();
            $table->text('headline')->nullable();
            $table->text('bio')->nullable();
            $table->string('bio_language')->nullable(); // fr | en (language-tag user text, doc 09)
            $table->smallInteger('verification_tier')->default(0); // 0..3 (doc 04)
            $table->decimal('rating_avg', 3, 2)->nullable(); // cached, derived — never authoritative
            $table->integer('rating_count')->default(0);
            $table->integer('jobs_completed')->default(0);
            $table->boolean('accepts_direct')->default(true);
            $table->boolean('accepts_dispatch')->default(false);
            $table->boolean('accepts_bidding')->default(false);
            $table->timestampTz('suspended_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('service_areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_profile_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->geography('center', subtype: 'point', srid: 4326);
            $table->integer('radius_m');
            $table->timestampTz('created_at')->useCurrent();

            $table->index('provider_profile_id');
            $table->spatialIndex('center'); // GIST — dispatch ranking is ST_DWithin over this
        });
        DB::statement('ALTER TABLE service_areas ADD CONSTRAINT service_areas_radius_check
            CHECK (radius_m BETWEEN 500 AND 100000)');

        Schema::create('provider_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('provider_profile_id')->constrained('provider_profiles')->cascadeOnDelete();
            $table->foreignUuid('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->bigInteger('rate_minor')->nullable();
            $table->char('currency', 3)->default('XAF');
            $table->smallInteger('years_experience')->nullable();
            $table->timestampsTz();

            $table->unique(['provider_profile_id', 'skill_id']);
        });
        DB::statement('ALTER TABLE provider_skills ADD COLUMN price_model price_model NOT NULL');
        DB::statement('ALTER TABLE provider_skills ADD CONSTRAINT provider_skills_rate_check
            CHECK (rate_minor IS NULL OR rate_minor >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_skills');
        Schema::dropIfExists('service_areas');
        Schema::dropIfExists('provider_profiles');
        DB::statement('DROP TYPE IF EXISTS price_model');
    }
};
