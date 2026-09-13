<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The payment METHOD, on every collection and every payout (founder decision 2026-09-13: MTN Mobile
 * Money, Orange Money and cash are the product's payment methods — nothing else).
 *
 * The gateway had been asked for an undifferentiated "mobile money" collection and left the payer
 * to pick an operator on a hosted page; the row recorded nothing about which rail the money took.
 * Reconciliation, the payout ledger and the customer's receipt all want to know. Existing rows are
 * classified from the phone's prefix, which is how the app pre-selects the choice too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table): void {
            $table->string('method', 16)->nullable()->after('gateway');
        });
        Schema::table('payouts', function (Blueprint $table): void {
            $table->string('method', 16)->nullable()->after('gateway');
        });

        foreach (['payment_intents', 'payouts'] as $tbl) {
            // MTN: 650–654, 67x, 680–684. Orange: 655–659, 69x, 685–689. Local nine-digit form after
            // an optional +237 / 237.
            DB::statement(<<<SQL
                UPDATE {$tbl} SET method = CASE
                    WHEN regexp_replace(regexp_replace(msisdn, '\\D', '', 'g'), '^237', '') ~ '^6(5[0-4]|7[0-9]|8[0-4])' THEN 'mtn_momo'
                    WHEN regexp_replace(regexp_replace(msisdn, '\\D', '', 'g'), '^237', '') ~ '^6(5[5-9]|9[0-9]|8[5-9])' THEN 'orange_money'
                    ELSE method
                END
                WHERE method IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('payment_intents', fn (Blueprint $table) => $table->dropColumn('method'));
        Schema::table('payouts', fn (Blueprint $table) => $table->dropColumn('method'));
    }
};
