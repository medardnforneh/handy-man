<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skills taxonomy (build plan P1-07, doc 02). Self-referencing: top-level rows are categories,
 * children are leaf skills. Bilingual by paired columns (name_fr / name_en) — the doc's chosen
 * pattern for a fixed 2-language, few-field catalog entity.
 *
 * P1-07b: full-text search must use the config that MATCHES the text's language, so we build a GIN
 * index per language ('french' on name_fr, 'english' on name_en). A French query stems with the
 * French dictionary, an English query with the English one.
 *
 * `risk_tier` (1..3) drives the required verification tier for accepting an on-site paid job (doc 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->text('name_fr');
            $table->text('name_en');
            $table->boolean('is_leaf')->default(true);
            $table->boolean('requires_license')->default(false);
            $table->smallInteger('risk_tier')->default(1);
            $table->timestampsTz();

            $table->index('parent_id');
        });

        // Self-referencing FK added after the table (and its primary key) exists.
        Schema::table('skills', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('skills')->nullOnDelete();
        });

        DB::statement('ALTER TABLE skills ADD COLUMN slug citext NOT NULL');
        DB::statement('ALTER TABLE skills ADD CONSTRAINT skills_slug_unique UNIQUE (slug)');
        DB::statement('ALTER TABLE skills ADD CONSTRAINT skills_risk_tier_check CHECK (risk_tier BETWEEN 1 AND 3)');

        // Language-specific FTS (P1-07b) — index the matching config per text.
        DB::statement("CREATE INDEX skills_name_fr_fts ON skills USING gin (to_tsvector('french', name_fr))");
        DB::statement("CREATE INDEX skills_name_en_fts ON skills USING gin (to_tsvector('english', name_en))");
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
