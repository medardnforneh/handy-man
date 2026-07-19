<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The org boundary for assignments (build plan P2-08) as a DB guarantee, not just app policy: a
 * worker assigned to an engagement MUST belong to that engagement's provider —
 *
 *   - individual provider → the worker is the provider's own user (`users.party_id = provider`);
 *   - organization provider → the worker has an ACTIVE membership in that org.
 *
 * This makes "a dispatcher of org A cannot assign a worker of org B" true at the database level,
 * independent of any controller. A CHECK can't cross tables, so it's a constraint trigger (same
 * pattern as `assert_party_kind`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // The moment a worker was removed (soft removal keeps the audit trail; the
        // one_lead_per_engagement partial index already excludes status = 'removed').
        Schema::table('assignments', function (Blueprint $table): void {
            $table->timestampTz('removed_at')->nullable();
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_assignment_worker_belongs() RETURNS trigger AS $$
            DECLARE
                prov_party uuid;
                prov_kind  party_kind;
                ok         boolean;
            BEGIN
                SELECT e.provider_party_id INTO prov_party FROM engagements e WHERE e.id = NEW.engagement_id;
                IF prov_party IS NULL THEN
                    RAISE EXCEPTION 'engagement % does not exist', NEW.engagement_id;
                END IF;

                SELECT kind INTO prov_kind FROM parties WHERE id = prov_party;

                IF prov_kind = 'individual' THEN
                    SELECT EXISTS(
                        SELECT 1 FROM users u
                        WHERE u.id = NEW.worker_user_id AND u.party_id = prov_party
                    ) INTO ok;
                ELSE
                    SELECT EXISTS(
                        SELECT 1
                        FROM memberships m
                        JOIN organizations o ON o.id = m.organization_id
                        WHERE m.user_id = NEW.worker_user_id
                          AND o.party_id = prov_party
                          AND m.status = 'active'
                    ) INTO ok;
                END IF;

                IF NOT ok THEN
                    RAISE EXCEPTION 'worker % does not belong to the provider of engagement %',
                        NEW.worker_user_id, NEW.engagement_id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement('CREATE CONSTRAINT TRIGGER assignments_worker_boundary_check
            AFTER INSERT OR UPDATE ON assignments
            FOR EACH ROW EXECUTE FUNCTION assert_assignment_worker_belongs()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS assignments_worker_boundary_check ON assignments');
        DB::statement('DROP FUNCTION IF EXISTS assert_assignment_worker_belongs()');
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropColumn('removed_at');
        });
    }
};
