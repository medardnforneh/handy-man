<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Share-my-job links (build plan P6-05, doc 04). A participant shares a signed, expiring, revocable
 * link with a family member showing the provider's first name, the approximate job location and the
 * live status — cheap to build, disproportionately reassuring for on-site work. The token is stored
 * hashed (only the URL holds the secret), and the link expires and can be revoked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique(); // sha256 of the opaque URL token
            $table->foreignUuid('created_by_user_id')->constrained('users');
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_shares');
    }
};
