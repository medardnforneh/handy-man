<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devices (build plan P1-04, doc 02). The client generates a stable device id (sent as the
 * `X-Device-Id` header) — we use it as the primary key so re-registration is an upsert. Captures
 * the push token (FCM) and the app version that drives the force-update kill switch (P0-08).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary(); // == client X-Device-Id
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform'); // android | ios | web
            $table->text('push_token')->nullable();
            $table->string('app_version'); // drives the force-update kill switch
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            // A push token belongs to at most one device per user.
            $table->unique(['user_id', 'push_token']);
        });

        DB::statement("ALTER TABLE devices ADD CONSTRAINT devices_platform_check
            CHECK (platform IN ('android','ios','web'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
