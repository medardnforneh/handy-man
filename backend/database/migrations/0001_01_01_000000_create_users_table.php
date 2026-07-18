<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identity schema (build plan P1-01, doc 02). Both sides of the marketplace can be an individual
 * or a company, so identity is modelled as a `parties` supertype with `users` (individuals) and
 * `organizations` (companies) as subtypes, plus `memberships` linking users to organizations.
 *
 * All PKs are UUID (doc 02) — time-ordered, app-generated (see the models' HasUuids). Phone is the
 * primary identifier (email penetration is low); OTP-first, password optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Postgres native enums (CLAUDE.md naming). Only the identity enums are created here; the
        // rest are created in the phase that first needs them.
        DB::statement("CREATE TYPE party_kind AS ENUM ('individual','organization')");
        DB::statement("CREATE TYPE user_status AS ENUM ('pending','active','suspended','closed')");
        DB::statement("CREATE TYPE membership_role AS ENUM ('owner','admin','dispatcher','finance','worker')");

        Schema::create('parties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('display_name');
            $table->timestampsTz();
        });
        // kind + status are native enums — add via raw SQL (Blueprint has no native-enum helper).
        DB::statement('ALTER TABLE parties ADD COLUMN kind party_kind NOT NULL');
        DB::statement("ALTER TABLE parties ADD COLUMN status user_status NOT NULL DEFAULT 'pending'");

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('party_id')->unique();
            $table->foreign('party_id')->references('id')->on('parties')->cascadeOnDelete();
            // citext for case-insensitive uniqueness (phone is canonical E.164; email varies in case).
            $table->timestampTz('phone_verified_at')->nullable();
            $table->timestampTz('email_verified_at')->nullable();
            $table->text('password_hash')->nullable(); // OTP-only accounts are valid
            $table->string('remember_token', 100)->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement('ALTER TABLE users ADD COLUMN phone_e164 citext NOT NULL');
        DB::statement('ALTER TABLE users ADD COLUMN email citext NULL');
        DB::statement("ALTER TABLE users ADD COLUMN locale text NOT NULL DEFAULT 'fr'");
        DB::statement("ALTER TABLE users ADD COLUMN comms_locale text NOT NULL DEFAULT 'fr'"); // P1-05b
        DB::statement("ALTER TABLE users ADD COLUMN status user_status NOT NULL DEFAULT 'pending'");
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_phone_e164_unique UNIQUE (phone_e164)');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email)');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_locale_supported CHECK (locale IN ('fr','en'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_comms_locale_supported CHECK (comms_locale IN ('fr','en'))");

        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('party_id')->unique();
            $table->foreign('party_id')->references('id')->on('parties')->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('rccm_number')->nullable(); // Registre du Commerce
            $table->string('niu')->nullable();         // Numéro d'Identifiant Unique (tax)
            $table->timestampsTz();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('organization_id');
            $table->string('status')->default('active');
            $table->uuid('invited_by_user_id')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('invited_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->unique(['user_id', 'organization_id']);
        });
        DB::statement('ALTER TABLE memberships ADD COLUMN role membership_role NOT NULL');

        // A CHECK can't cross tables, so enforce the party-kind rule with constraint triggers
        // (doc 02): a users row must reference an 'individual' party; an organizations row an
        // 'organization' party. Deferred is unnecessary — the party exists before its subtype.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION assert_party_kind() RETURNS trigger AS $$
            DECLARE
                actual party_kind;
            BEGIN
                SELECT kind INTO actual FROM parties WHERE id = NEW.party_id;
                IF actual IS NULL THEN
                    RAISE EXCEPTION 'party % does not exist', NEW.party_id;
                END IF;
                IF actual <> TG_ARGV[0]::party_kind THEN
                    RAISE EXCEPTION 'party % is % but %.party_id requires %',
                        NEW.party_id, actual, TG_TABLE_NAME, TG_ARGV[0];
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement("CREATE CONSTRAINT TRIGGER users_party_kind_check AFTER INSERT OR UPDATE ON users
            FOR EACH ROW EXECUTE FUNCTION assert_party_kind('individual')");
        DB::statement("CREATE CONSTRAINT TRIGGER organizations_party_kind_check AFTER INSERT OR UPDATE ON organizations
            FOR EACH ROW EXECUTE FUNCTION assert_party_kind('organization')");

        // Laravel framework tables — password reset (email-based) and sessions.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index(); // uuid now that users.id is uuid
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('users');
        Schema::dropIfExists('parties');
        DB::statement('DROP FUNCTION IF EXISTS assert_party_kind()');
        DB::statement('DROP TYPE IF EXISTS membership_role');
        DB::statement('DROP TYPE IF EXISTS user_status');
        DB::statement('DROP TYPE IF EXISTS party_kind');
    }
};
