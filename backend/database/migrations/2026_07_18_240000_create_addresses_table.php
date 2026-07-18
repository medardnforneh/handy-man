<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Addresses (build plan P1-06, doc 02). Location is `geography(Point,4326)` with a GIST index so
 * `ST_DWithin` proximity search stays fast at scale. Geography is optional per engagement_mode
 * (remote work has none) — but an address, when it exists, always carries a point.
 *
 * Quarter (neighbourhood) and a landmark note ("behind the Total station") reflect real Cameroonian
 * addressing, which is often more meaningful than a street line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('line1');
            $table->string('quarter')->nullable();
            $table->string('city');
            $table->string('region')->nullable();
            $table->char('country_code', 2)->default('CM');
            $table->geography('point', subtype: 'point', srid: 4326);
            $table->text('landmark_note')->nullable();
            $table->timestampsTz();

            $table->index('party_id');
            $table->spatialIndex('point'); // GIST — ST_DWithin under 50ms at 100k rows
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
