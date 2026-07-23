<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Do-not-contact list (build plan P7-08, doc 07). Part of the provider CRM: a provider marks a
 * customer do-not-contact and it is honoured absolutely — a manual follow-up to that customer is
 * refused. This is what makes a provider's client book feel like an asset held on the platform, and
 * keeps a provider from spamming customers through it (a reputation problem for the platform).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('do_not_contacts', function (Blueprint $table) {
            $table->foreignUuid('provider_party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignUuid('customer_party_id')->constrained('parties')->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['provider_party_id', 'customer_party_id']);
        });
        DB::statement('ALTER TABLE do_not_contacts ADD CONSTRAINT do_not_contacts_distinct_check
            CHECK (provider_party_id <> customer_party_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('do_not_contacts');
    }
};
