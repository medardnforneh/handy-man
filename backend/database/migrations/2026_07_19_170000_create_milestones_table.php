<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestones (build plan P2.5-05, doc 06) and the second path into `engagements`: an accepted
 * quotation. Both the offer path (P2-06) and the quote path converge here, so `engagements` gains a
 * nullable `quotation_id`, `offer_id` becomes nullable, and a CHECK requires exactly one origin.
 *
 * The money invariant: for any engagement that HAS milestones, they must sum to the agreed amount.
 * Enforced by a DEFERRABLE INITIALLY DEFERRED constraint trigger so the engagement and its milestones
 * can be inserted in one transaction and validated at commit. Offer-path engagements carry no
 * milestones, so the check is a no-op for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Engagements: a quotation is the other way in.
        DB::statement('ALTER TABLE engagements ALTER COLUMN offer_id DROP NOT NULL');
        Schema::table('engagements', function (Blueprint $table): void {
            $table->foreignUuid('quotation_id')->nullable()->after('offer_id')->constrained('quotations');
        });
        DB::statement('ALTER TABLE engagements ADD CONSTRAINT engagements_origin_check
            CHECK (offer_id IS NOT NULL OR quotation_id IS NOT NULL)');

        DB::statement("CREATE TYPE milestone_status AS ENUM ('pending','in_progress','submitted','approved','rejected','paid')");

        Schema::create('milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('engagement_id')->constrained('engagements')->cascadeOnDelete();
            $table->smallInteger('position');
            $table->string('title');
            $table->bigInteger('amount_minor')->default(0);
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['engagement_id', 'position']);
        });
        DB::statement("ALTER TABLE milestones ADD COLUMN status milestone_status NOT NULL DEFAULT 'pending'");
        DB::statement('ALTER TABLE milestones ADD CONSTRAINT milestones_amount_check CHECK (amount_minor >= 0)');

        // SUM(milestones.amount_minor) = engagements.agreed_amount_minor — but only when milestones
        // exist. Deferred so a quote acceptance can write the engagement + its milestones atomically.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_milestones_sum() RETURNS trigger AS $$
            DECLARE
                eng_id uuid;
                agreed bigint;
                total  bigint;
                cnt    int;
            BEGIN
                eng_id := COALESCE(NEW.engagement_id, OLD.engagement_id);
                SELECT agreed_amount_minor INTO agreed FROM engagements WHERE id = eng_id;
                IF agreed IS NULL THEN
                    RETURN NULL; -- engagement was deleted (cascade); nothing to check
                END IF;
                SELECT COUNT(*), COALESCE(SUM(amount_minor), 0) INTO cnt, total
                FROM milestones WHERE engagement_id = eng_id;
                IF cnt > 0 AND total <> agreed THEN
                    RAISE EXCEPTION 'milestones for engagement % sum to % but the agreed amount is %',
                        eng_id, total, agreed;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('CREATE CONSTRAINT TRIGGER milestones_sum_matches_agreed
            AFTER INSERT OR UPDATE OR DELETE ON milestones
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION assert_milestones_sum()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS milestones_sum_matches_agreed ON milestones');
        DB::statement('DROP FUNCTION IF EXISTS assert_milestones_sum()');
        Schema::dropIfExists('milestones');
        DB::statement('DROP TYPE IF EXISTS milestone_status');

        DB::statement('ALTER TABLE engagements DROP CONSTRAINT IF EXISTS engagements_origin_check');
        Schema::table('engagements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quotation_id');
        });
        // Note: offer_id is left nullable on rollback (harmless).
    }
};
