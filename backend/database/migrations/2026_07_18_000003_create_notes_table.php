<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `notes` — the table behind the P0-05 REFERENCE vertical slice.
 *
 * This is not a product feature. It exists so the codebase has ONE worked example wiring together
 * every layer the conventions require (migration → model → factory → Action → Policy → Request →
 * Resource → thin controller → Pest test), including the idempotency + outbox infrastructure.
 * Phase 1 features copy this shape. See CLAUDE.md "The worked vertical slice (reference)".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
