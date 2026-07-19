<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quotations — versioned and immutable (build plan P2.5-01/02/03, doc 06).
 *
 * A submitted quote is a decision artefact: its TERMS never change. Revision = a new version row
 * with `supersedes_id`, the old one marked `superseded` (CLAUDE.md rule #9). Enforced in the DB:
 *
 *   - `assert_quote_terms_immutable` rejects any UPDATE that changes a non-draft quote's terms
 *     (amounts, dates, notes, version chain). Status/response transitions are still allowed.
 *   - `assert_quote_lines_frozen` freezes a quote's lines once it leaves draft.
 *   - `one_live_quote_per_provider_per_job` (partial unique) allows only one draft/submitted quote
 *     per provider per job — a second live draft is rejected by the DB (P2.5-02).
 *
 * The three dates (P2.5-03) are distinct claims by two parties; only `provider_committed_at` feeds
 * the on-time-rate metric later.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE quote_status AS ENUM ('draft','submitted','accepted','rejected','expired','withdrawn','superseded')");

        Schema::create('quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('job_id')->constrained('service_jobs');
            $table->foreignUuid('provider_party_id')->constrained('parties');
            $table->integer('version');
            $table->uuid('supersedes_id')->nullable();
            $table->char('currency', 3)->default('XAF');
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('deposit_minor')->default(0);
            $table->text('notes')->nullable();
            // The three dates (doc 06).
            $table->timestampTz('customer_requested_by')->nullable();
            $table->timestampTz('provider_estimated_at')->nullable();
            $table->timestampTz('provider_committed_at')->nullable();
            $table->timestampTz('valid_until');
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('responded_at')->nullable();
            $table->timestampsTz();

            $table->unique(['job_id', 'provider_party_id', 'version']);
        });
        // Self-referential FK added after the table (and its PK) exist.
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT quotations_supersedes_id_foreign
            FOREIGN KEY (supersedes_id) REFERENCES quotations(id)');
        DB::statement("ALTER TABLE quotations ADD COLUMN status quote_status NOT NULL DEFAULT 'draft'");
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT quotations_subtotal_check CHECK (subtotal_minor >= 0)');
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT quotations_deposit_check CHECK (deposit_minor >= 0 AND deposit_minor <= subtotal_minor)');

        // Only one live (draft|submitted) quote per provider per job.
        DB::statement("CREATE UNIQUE INDEX one_live_quote_per_provider_per_job
            ON quotations (job_id, provider_party_id)
            WHERE status IN ('draft','submitted')");

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->smallInteger('position');
            $table->string('kind'); // labour | material | travel | other
            $table->string('label');
            $table->decimal('quantity', 12, 3);
            $table->bigInteger('unit_price_minor');
            $table->timestampsTz();

            $table->unique(['quotation_id', 'position']);
        });
        DB::statement('ALTER TABLE quotation_lines ADD CONSTRAINT quotation_lines_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE quotation_lines ADD CONSTRAINT quotation_lines_unit_price_check CHECK (unit_price_minor >= 0)');

        // A non-draft quote's TERMS are immutable; only status/response fields may move.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_quote_terms_immutable() RETURNS trigger AS $$
            BEGIN
                IF OLD.status <> 'draft' AND (
                       NEW.subtotal_minor <> OLD.subtotal_minor
                    OR NEW.deposit_minor <> OLD.deposit_minor
                    OR NEW.currency <> OLD.currency
                    OR NEW.version <> OLD.version
                    OR NEW.job_id <> OLD.job_id
                    OR NEW.provider_party_id <> OLD.provider_party_id
                    OR NEW.notes IS DISTINCT FROM OLD.notes
                    OR NEW.customer_requested_by IS DISTINCT FROM OLD.customer_requested_by
                    OR NEW.provider_estimated_at IS DISTINCT FROM OLD.provider_estimated_at
                    OR NEW.provider_committed_at IS DISTINCT FROM OLD.provider_committed_at
                    OR NEW.valid_until IS DISTINCT FROM OLD.valid_until
                    OR NEW.supersedes_id IS DISTINCT FROM OLD.supersedes_id
                ) THEN
                    RAISE EXCEPTION 'quotation % is % and its terms are immutable; revise with a new version', OLD.id, OLD.status;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
        DB::statement('CREATE TRIGGER quotations_terms_immutable BEFORE UPDATE ON quotations
            FOR EACH ROW EXECUTE FUNCTION assert_quote_terms_immutable()');

        // A quote's lines are frozen once it leaves draft (no insert/update/delete).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_quote_lines_frozen() RETURNS trigger AS $$
            DECLARE
                q_status quote_status;
                q_id uuid;
            BEGIN
                q_id := COALESCE(NEW.quotation_id, OLD.quotation_id);
                SELECT status INTO q_status FROM quotations WHERE id = q_id;
                IF q_status IS NOT NULL AND q_status <> 'draft' THEN
                    RAISE EXCEPTION 'quotation % is % and its lines are frozen', q_id, q_status;
                END IF;
                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        SQL);
        DB::statement('CREATE TRIGGER quotation_lines_frozen BEFORE INSERT OR UPDATE OR DELETE ON quotation_lines
            FOR EACH ROW EXECUTE FUNCTION assert_quote_lines_frozen()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS quotation_lines_frozen ON quotation_lines');
        DB::statement('DROP TRIGGER IF EXISTS quotations_terms_immutable ON quotations');
        DB::statement('DROP FUNCTION IF EXISTS assert_quote_lines_frozen()');
        DB::statement('DROP FUNCTION IF EXISTS assert_quote_terms_immutable()');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
        DB::statement('DROP TYPE IF EXISTS quote_status');
    }
};
