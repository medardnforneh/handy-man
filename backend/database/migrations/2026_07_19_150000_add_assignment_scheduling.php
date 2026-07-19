<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Worker scheduling + double-booking prevention (build plan P2-09): the same worker can never hold
 * two active assignments whose time windows overlap.
 *
 * The app writes two plain `timestamptz` bounds; a GENERATED `tstzrange` is derived from them so the
 * model never has to marshal a range. A GiST `EXCLUDE` constraint (needs `btree_gist` for the
 * `worker_user_id WITH =` equality) is the hard guarantee — overlapping windows for one worker are
 * rejected at the database, independent of any app check. Rows with no window (NULL range) and
 * `removed` assignments don't participate.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::table('assignments', function (Blueprint $table): void {
            $table->timestampTz('scheduled_from')->nullable();
            $table->timestampTz('scheduled_to')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE assignments ADD COLUMN scheduled_window tstzrange
            GENERATED ALWAYS AS (
                CASE
                    WHEN scheduled_from IS NOT NULL AND scheduled_to IS NOT NULL
                    THEN tstzrange(scheduled_from, scheduled_to, '[)')
                END
            ) STORED
        SQL);

        // A window must be well-formed.
        DB::statement('ALTER TABLE assignments ADD CONSTRAINT assignments_window_order
            CHECK (scheduled_from IS NULL OR scheduled_to IS NULL OR scheduled_to > scheduled_from)');

        // The hard guarantee: no two active assignments of one worker may overlap in time.
        DB::statement('ALTER TABLE assignments ADD CONSTRAINT assignments_no_double_booking
            EXCLUDE USING gist (worker_user_id WITH =, scheduled_window WITH &&)
            WHERE (status <> \'removed\')');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assignments DROP CONSTRAINT IF EXISTS assignments_no_double_booking');
        DB::statement('ALTER TABLE assignments DROP CONSTRAINT IF EXISTS assignments_window_order');
        DB::statement('ALTER TABLE assignments DROP COLUMN IF EXISTS scheduled_window');
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn(['scheduled_from', 'scheduled_to']);
        });
    }
};
