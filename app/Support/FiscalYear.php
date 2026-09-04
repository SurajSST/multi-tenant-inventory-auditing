<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The Nepali fiscal year runs Shrawan 1 to the end of Ashad, which lands on
 * roughly 16 July to 15 July in the Gregorian calendar.
 *
 * This is the same approximation the reference backend uses. If exact Bikram
 * Sambat dates ever matter for reporting, swap in a proper conversion library —
 * only this class would change.
 */
class FiscalYear
{
    /** AD year + 56 or 57 = BS year. */
    private const BS_OFFSET = 56;

    /** "2082/83" */
    public static function label(?CarbonInterface $at = null): string
    {
        $at = $at ? Carbon::instance($at) : Carbon::now();

        $afterShrawan1 = $at->month > 7 || ($at->month === 7 && $at->day >= 16);
        $start = $at->year + self::BS_OFFSET + ($afterShrawan1 ? 1 : 0);
        $next = str_pad((string) (($start + 1) % 100), 2, '0', STR_PAD_LEFT);

        return "{$start}/{$next}";
    }

    /** "2082" — the part that goes into a DF / PO / PC reference. */
    public static function startYear(?CarbonInterface $at = null): string
    {
        return explode('/', self::label($at))[0];
    }
}
