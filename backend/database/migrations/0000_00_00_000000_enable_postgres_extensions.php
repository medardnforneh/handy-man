<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Runs first (timestamp 0000_00_00) so every later migration can rely on PostGIS geography
 * types, citext columns and pg_trgm indexes. These are cluster capabilities, not app tables —
 * enabling them here keeps a fresh dev DB and `migrate:fresh` in lockstep with CI.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
        DB::statement('DROP EXTENSION IF EXISTS citext');
        DB::statement('DROP EXTENSION IF EXISTS postgis');
    }
};
