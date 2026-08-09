<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How often a trade genuinely needs doing again (build plan P7-07, `maintenance_due`).
 *
 * NULLABLE on purpose, and null for most trades. A serviced air conditioner really does want looking
 * at again in six months; a wardrobe built once does not, and nudging someone about "maintenance" on
 * a one-off carpentry job is the kind of message that teaches people to ignore every message. Only
 * trades where recurring service is a real fact of the work carry an interval — everything else
 * schedules nothing at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table): void {
            $table->integer('maintenance_interval_days')->nullable();
        });

        DB::statement('ALTER TABLE skills ADD CONSTRAINT skills_maintenance_interval_check
            CHECK (maintenance_interval_days IS NULL OR maintenance_interval_days > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE skills DROP CONSTRAINT IF EXISTS skills_maintenance_interval_check');

        Schema::table('skills', function (Blueprint $table): void {
            $table->dropColumn('maintenance_interval_days');
        });
    }
};
