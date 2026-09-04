<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three gaps the audit found: rules the services enforced in PHP but the
 * database did not, so a second concurrent request — or anybody with a mysql
 * prompt — could walk straight past them.
 *
 *   1. One approved demand could carry two purchase orders.
 *   2. One bill could carry two live petty cash tokens.
 *   3. Nothing stopped a bill being claimed from petty cash AND entered in the
 *      main register. That is the classic double claim, and it was only blocked
 *      in one direction.
 *
 * (3) is a cross-table rule, so it lives with the rest of the controls in
 * App\Support\IntegrityRules. This migration adds the two structural uniques.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One approved demand, one order. The service already refused a second
        // one, but only after a read that another request could interleave with.
        // demand_id is a UUID, so this needs no tenant scope to be correct — an
        // id from another school could never collide with one from this one.
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unique('demand_id', 'uniq_order_per_demand');
        });

        // One live token per bill number, WITHIN a school. A voided token
        // releases the number, which is why this keys off a generated column
        // rather than bill_no itself — NULL repeats freely in a unique index,
        // a bill number does not.
        DB::statement("
            ALTER TABLE petty_cash_tokens
              ADD COLUMN bill_claim_key VARCHAR(255)
                AS (CASE WHEN status = 'VOIDED' THEN NULL ELSE bill_no END) PERSISTENT,
              ADD UNIQUE KEY uniq_live_token_per_bill (tenant_id, bill_claim_key)
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE petty_cash_tokens DROP INDEX uniq_live_token_per_bill, DROP COLUMN bill_claim_key');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('uniq_order_per_demand');
        });
    }
};
