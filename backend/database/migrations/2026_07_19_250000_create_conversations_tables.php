<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The engagement workspace conversation (build plan P4-01, doc 06). The chat IS the state machine:
 * every transition emits a structured message into the thread, so the conversation is the timeline,
 * the audit log and the UI at once.
 *
 * Structured messages (`kind` <> 'text'/'voice'/'media') are narrated by the server — the Action that
 * performs a transition inserts them in its own transaction (CLAUDE.md rule #11). A client may only
 * ever post free-form messages; the message endpoint rejects structured kinds.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE message_kind AS ENUM (
            'text','voice','media','document','system',
            'quote_submitted','quote_revised','quote_accepted','quote_rejected',
            'milestone_submitted','milestone_approved','milestone_rejected',
            'site_visit_proposed','site_visit_confirmed',
            'on_the_way','arrived','started','paused','resumed','completed',
            'payment_requested','payment_received','deliverable_submitted'
        )");

        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->unique()->constrained('service_jobs');
            $table->timestampsTz();
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('party_id')->constrained('parties');
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestampTz('joined_at')->useCurrent();
            $table->timestampTz('last_read_at')->nullable();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_user_id')->nullable()->constrained('users'); // null = pure system narration
            $table->text('body')->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('contact_flag')->nullable(); // 'phone' | 'email' — detected, logged, NOT blocked (v1)
            $table->uuid('reply_to_id')->nullable();
            $table->timestampTz('edited_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();

            $table->index(['conversation_id', 'created_at']);
        });
        DB::statement("ALTER TABLE messages ADD COLUMN kind message_kind NOT NULL DEFAULT 'text'");
        // Self-referential FK added after the table (and its PK) exist.
        DB::statement('ALTER TABLE messages ADD CONSTRAINT messages_reply_to_id_foreign
            FOREIGN KEY (reply_to_id) REFERENCES messages(id)');

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('emoji');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['message_id', 'user_id', 'emoji']);
        });

        Schema::create('message_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();

            $table->unique(['message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_receipts');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
        DB::statement('DROP TYPE IF EXISTS message_kind');
    }
};
