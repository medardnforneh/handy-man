<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds `warranty_issued` to the message_kind enum (P6-11).
 *
 * A warranty had no read endpoint and was never narrated, which left the customer it protects
 * unable to learn it existed — and unable to claim against it, since the claim endpoint needs an id
 * nothing would ever hand them. Narrating it into the thread is how they find out, so the thread
 * has to be able to hold the kind.
 *
 * `ADD VALUE IF NOT EXISTS` cannot run inside a transaction on PostgreSQL, hence the explicit
 * non-transactional flag. There is no down(): PostgreSQL cannot drop a value from an enum type, and
 * pretending otherwise in a rollback would leave the schema in a state the code does not expect.
 */
return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TYPE message_kind ADD VALUE IF NOT EXISTS 'warranty_issued'");
    }
};
