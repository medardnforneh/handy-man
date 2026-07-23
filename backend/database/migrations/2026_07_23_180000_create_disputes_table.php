<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Disputes (build plan P6-10, doc 04). A party raises a dispute on an engagement; a human admin
 * adjudicates. Any money movement from an adjudication is a **balanced adjustment transaction**
 * stamped with the admin's id — never an edit of history (rule #8/#9). The dispute row links to that
 * transaction, so every adjudication is attributable to a named admin and to its ledger effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->foreignUuid('raised_by_party_id')->constrained('parties');
            $table->string('category');
            $table->text('body');
            $table->string('status')->default('open');
            $table->text('resolution_note')->nullable();
            $table->foreignUuid('resolution_transaction_id')->nullable()->constrained('ledger_transactions');
            $table->foreignUuid('resolved_by_user_id')->nullable()->constrained('users');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE disputes ADD CONSTRAINT disputes_category_check
            CHECK (category IN ('quality','payment','no_show','scope','safety','other'))");
        DB::statement("ALTER TABLE disputes ADD CONSTRAINT disputes_status_check
            CHECK (status IN ('open','reviewing','resolved','rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
