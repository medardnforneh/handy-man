<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verification documents (build plan P6-01, doc 02/04). Identity/licence documents captured for
 * human admin review — the ONLY verification available here (no API background check operates in
 * Cameroon; doc 04). Stored in a bucket separate from public media, encrypted at rest, and only ever
 * served through a signed short-TTL URL — never a public or permanent link.
 *
 * `party_id` is whose verification this is; `subject_user_id` names the specific human when the party
 * is an organization. An approved document is what raises `provider_profiles.verification_tier`,
 * which feeds the `identity_verified` fact (P6-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TYPE doc_kind AS ENUM (
            'national_id_front','national_id_back','selfie','trade_license','insurance_cert','rccm','niu')");
        DB::statement("CREATE TYPE doc_status AS ENUM ('pending','approved','rejected','expired')");

        Schema::create('verification_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained('parties');
            $table->foreignUuid('subject_user_id')->nullable()->constrained('users');
            $table->text('storage_path');           // encrypted bucket, separate from public media
            $table->char('sha256', 64);             // of the plaintext, so re-uploads are detectable
            $table->smallInteger('grants_tier')->default(0); // the tier this doc unlocks once approved
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users');
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE verification_documents ADD COLUMN kind doc_kind NOT NULL');
        DB::statement("ALTER TABLE verification_documents ADD COLUMN status doc_status NOT NULL DEFAULT 'pending'");
        DB::statement('CREATE INDEX verification_documents_party_status_idx ON verification_documents (party_id, status)');
        DB::statement('ALTER TABLE verification_documents ADD CONSTRAINT verification_documents_grants_tier_check
            CHECK (grants_tier BETWEEN 0 AND 3)');
        // A rejection must carry a reason; an approval must not masquerade as pending.
        DB::statement("ALTER TABLE verification_documents ADD CONSTRAINT verification_documents_reject_reason_check
            CHECK (status <> 'rejected' OR reject_reason IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_documents');
        DB::statement('DROP TYPE IF EXISTS doc_status');
        DB::statement('DROP TYPE IF EXISTS doc_kind');
    }
};
