<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Referral fraud controls (build plan P8-02, doc 04). A referrer over the weekly velocity limit has
 * their new referrals flagged for a human look instead of auto-qualifying — the manual review queue.
 * A flagged referral never books a reward until an admin clears it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->boolean('flagged_for_review')->default(false)->after('status');
            $table->text('flag_reason')->nullable()->after('flagged_for_review');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->dropColumn(['flagged_for_review', 'flag_reason']);
        });
    }
};
