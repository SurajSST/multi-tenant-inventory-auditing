<?php

namespace App\Support;

use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hands out DF-2082-0001, PO-2082-0001 and PC-2082-0001 style references.
 *
 * The counter is per school as well as per fiscal year, so every school's
 * numbering starts at 0001 and two schools can hold the same reference without
 * either of them knowing the other exists.
 *
 * The bump is a single atomic UPDATE, so two clerks pressing Save at the same
 * moment can never be given the same number: MariaDB serialises them on the
 * counter row's lock, and LAST_INSERT_ID(expr) is per-connection, so each
 * session reads back its own new value.
 *
 * The row is created first with a separate INSERT IGNORE, because on a genuine
 * insert LAST_INSERT_ID() would return the row's auto-increment id rather than
 * the counter value.
 */
class RefCounter
{
    /**
     * The highest number already handed out for this prefix and fiscal year AT
     * ONE SCHOOL. Used to recover if the counter row is ever behind the records
     * it is supposed to be numbering.
     *
     * These are raw queries, so no global scope applies to them — the tenant
     * predicate below is doing real work, not decorating. Without it one
     * school's numbering would jump to clear another school's highest.
     */
    private static function maxExisting(string $prefix, string $startYear, string $tenantId): int
    {
        $pattern = "{$prefix}-{$startYear}-%";

        $highest = function (string $table, string $column) use ($pattern, $tenantId) {
            if (! Schema::hasTable($table)) {
                return 0;
            }

            return DB::table($table)
                ->where('tenant_id', $tenantId)
                ->where($column, 'like', $pattern)
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX({$column}, '-', -1) AS UNSIGNED)) as m")
                ->value('m');
        };

        try {
            $max = match ($prefix) {
                'DF' => $highest('demand_forms', 'ref'),
                'PO' => $highest('purchase_orders', 'ref'),
                'PC' => $highest('petty_cash_tokens', 'serial'),
                default => 0,
            };

            return (int) ($max ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param  'DF'|'PO'|'PC'|'GRN'  $prefix
     * @return array{ref: string, fiscal_year: string}
     */
    public static function next(string $prefix, ?Carbon $at = null, ?string $tenantId = null): array
    {
        $fiscalYear = FiscalYear::label($at);
        $startYear = explode('/', $fiscalYear)[0];
        $tenantId ??= app(TenantContext::class)->idOrFail();
        $existingMax = self::maxExisting($prefix, $startYear, $tenantId);

        DB::table('ref_counters')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'prefix' => $prefix,
            'fiscal_year' => $fiscalYear,
            'last_number' => $existingMax,
        ]);

        if ($existingMax > 0) {
            DB::table('ref_counters')
                ->where('tenant_id', $tenantId)
                ->where('prefix', $prefix)
                ->where('fiscal_year', $fiscalYear)
                ->where('last_number', '<', $existingMax)
                ->update(['last_number' => $existingMax]);
        }

        DB::update(
            'UPDATE ref_counters
                SET last_number = LAST_INSERT_ID(last_number + 1)
              WHERE tenant_id = ? AND prefix = ? AND fiscal_year = ?',
            [$tenantId, $prefix, $fiscalYear]
        );

        $number = (int) DB::selectOne('SELECT LAST_INSERT_ID() AS n')->n;

        return [
            'ref' => sprintf('%s-%s-%04d', $prefix, $startYear, $number),
            'fiscal_year' => $fiscalYear,
        ];
    }
}
