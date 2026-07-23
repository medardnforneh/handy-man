<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-ups + comms log (build plan P7-01/03, doc 07). This is where retention lives. The whole
 * design turns on `dedupe_key` UNIQUE: scheduling is idempotent, so replaying an at-least-once outbox
 * event fifty times yields exactly one follow-up. `comms_log` records every actual send, which is how
 * the per-user per-channel budget is enforced (an over-budget follow-up is `suppressed`, not sent).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE followup_kind AS ENUM (
            'quote_pending_customer','quote_expiring','job_unquoted','site_visit_reminder',
            'job_starting_soon','check_in_overdue','awaiting_approval','auto_approve_warning',
            'review_request','review_reminder','payment_due','payout_ready',
            'warranty_expiring','maintenance_due','reengagement','abandoned_draft')");
        DB::statement("CREATE TYPE followup_channel AS ENUM ('in_app','push','sms','whatsapp','email')");
        DB::statement("CREATE TYPE followup_status AS ENUM ('scheduled','sent','cancelled','responded','failed','suppressed')");

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('target_party_id')->constrained('parties');
            $table->foreignUuid('target_user_id')->constrained('users');
            $table->foreignUuid('job_id')->nullable()->constrained('service_jobs');
            $table->foreignUuid('engagement_id')->nullable()->constrained('engagements');
            $table->foreignUuid('quotation_id')->nullable()->constrained('quotations');
            $table->foreignUuid('warranty_id')->nullable()->constrained('warranties');
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users'); // set for manual provider follow-ups (P7-08)
            $table->timestampTz('scheduled_for');
            $table->string('dedupe_key')->unique();
            $table->smallInteger('attempts')->default(0);
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->text('response_action')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE follow_ups ADD COLUMN kind followup_kind NOT NULL');
        DB::statement('ALTER TABLE follow_ups ADD COLUMN channel followup_channel NOT NULL');
        DB::statement("ALTER TABLE follow_ups ADD COLUMN status followup_status NOT NULL DEFAULT 'scheduled'");
        // The dispatch sweep reads only due, still-scheduled rows.
        DB::statement("CREATE INDEX follow_ups_due_idx ON follow_ups (scheduled_for) WHERE status = 'scheduled'");

        Schema::create('comms_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('purpose');
            $table->foreignUuid('follow_up_id')->nullable()->constrained('follow_ups');
            $table->timestampTz('sent_at')->useCurrent();
        });
        DB::statement('ALTER TABLE comms_log ADD COLUMN channel followup_channel NOT NULL');
        DB::statement('CREATE INDEX comms_log_budget_idx ON comms_log (user_id, channel, sent_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('comms_log');
        Schema::dropIfExists('follow_ups');
        DB::statement('DROP TYPE IF EXISTS followup_status');
        DB::statement('DROP TYPE IF EXISTS followup_channel');
        DB::statement('DROP TYPE IF EXISTS followup_kind');
    }
};
