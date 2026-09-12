<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per person, per day, per client platform they used the API from (doc 08, launch
 * checklist: "mobile-browser share of customer traffic instrumented — the switch trigger").
 *
 * Doc 08 keeps Flutter as the documented alternative to Ionic and names the trigger for switching:
 * mobile-APP usage, not mobile-web, proven to dominate. That is a share of daily active people by
 * platform, and it cannot be reconstructed after the fact — device registrations only count who
 * enabled push, and a web session leaves nothing behind. So it is recorded from launch, at the
 * cheapest grain that answers the question: a day, not a request. The recording middleware
 * throttles itself through the cache so the row is written once, not on every call.
 *
 * `platform` is one of android, ios, web_mobile, web_desktop — the split the trigger turns on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('platform', 16);
            $table->timestamp('first_seen_at');
            $table->unique(['user_id', 'day', 'platform']);
            $table->index(['day', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_days');
    }
};
