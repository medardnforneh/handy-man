<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safety: emergency contacts + safety alerts (build plan P6-04, doc 02/04). The panic button raises
 * a `safety_alert`, texts the user's emergency contacts, and alerts staff — all server-side, so it
 * works with the app backgrounded (the app only has to land the one request). A `check_in_overdue`
 * alert is raised by the watchdog (P6-06); no_show/unsafe_site/harassment can be raised manually.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE safety_alert_kind AS ENUM ('panic','no_show','unsafe_site','harassment','check_in_overdue')");

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->timestampTz('created_at')->useCurrent();
        });
        // phone_e164 as citext (case-insensitive), matching the users table convention.
        DB::statement('ALTER TABLE emergency_contacts ADD COLUMN phone_e164 citext NOT NULL');

        Schema::create('safety_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->foreignUuid('assignment_id')->nullable()->constrained('assignments');
            $table->geography('point', subtype: 'point', srid: 4326)->nullable();
            $table->text('note')->nullable();
            $table->string('status')->default('open');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignUuid('resolved_by_user_id')->nullable()->constrained('users');

            $table->index(['status', 'created_at']);
        });
        DB::statement('ALTER TABLE safety_alerts ADD COLUMN kind safety_alert_kind NOT NULL');
        DB::statement("ALTER TABLE safety_alerts ADD CONSTRAINT safety_alerts_status_check
            CHECK (status IN ('open','acknowledged','resolved'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_alerts');
        Schema::dropIfExists('emergency_contacts');
        DB::statement('DROP TYPE IF EXISTS safety_alert_kind');
    }
};
