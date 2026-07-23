<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews (build plan P6-08, doc 02/04). Two-way and double-blind: both parties to an engagement may
 * review each other, but nothing is visible until BOTH have submitted or the shared 14-day window
 * closes — then both publish at once. This kills retaliation and reciprocation (Airbnb's result), so
 * the signal stays honest. Build the reveal from the start; retrofitting it once every rating is 4.9
 * is pointless.
 *
 * `subject_worker_user_id` captures the specific worker when the provider is an org — the company's
 * public rating aggregates its workers, the per-worker rating stays internal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE review_visibility AS ENUM ('pending','published','withheld')");

        Schema::create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignUuid('author_party_id')->constrained('parties');
            $table->foreignUuid('subject_party_id')->constrained('parties');
            $table->foreignUuid('subject_worker_user_id')->nullable()->constrained('users');
            $table->smallInteger('rating');
            $table->text('body')->nullable();
            $table->text('private_note')->nullable(); // never published; visible to subject only
            $table->timestampTz('submitted_at')->useCurrent();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('window_closes_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['engagement_id', 'author_party_id']); // one review per author per engagement
            $table->index(['subject_party_id']);
        });

        DB::statement('ALTER TABLE reviews ADD COLUMN visibility review_visibility NOT NULL DEFAULT \'pending\'');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_not_self_check CHECK (author_party_id <> subject_party_id)');
        // Index for the reveal sweep: pending reviews whose window has closed.
        DB::statement("CREATE INDEX reviews_pending_window_idx ON reviews (window_closes_at) WHERE visibility = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        DB::statement('DROP TYPE IF EXISTS review_visibility');
    }
};
