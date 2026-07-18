<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactional outbox (build plan P0-07, CLAUDE.md "Architecture conventions").
 *
 * Anything that fans out — notifications, ledger side effects, webhooks, broadcast events — is
 * written here as a row in the SAME database transaction as the state change that caused it.
 * If that transaction rolls back, the outbox row rolls back with it, so nothing is ever published
 * for work that didn't commit. A separate relay worker later reads committed rows and dispatches
 * them exactly once. This is how we get atomic "change state AND announce it" without a
 * dispatch-inside-a-transaction that could fire for a rolled-back change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->bigIncrements('id');

            // What happened, e.g. 'engagement.created', 'ledger.entry.posted'. Dot-namespaced.
            $table->string('type');

            // Everything a handler needs. No PII beyond ids where avoidable (CLAUDE.md rule #6).
            $table->jsonb('payload');

            // Optional grouping for ordered processing per aggregate later (e.g. engagement id).
            $table->string('partition_key')->nullable();

            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('available_at')->useCurrent(); // for delayed publication
            $table->timestampTz('processed_at')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            // The relay scans for unprocessed rows that are due.
            $table->index(['processed_at', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
