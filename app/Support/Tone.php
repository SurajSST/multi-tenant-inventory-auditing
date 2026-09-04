<?php

namespace App\Support;

/**
 * The status-pill palette, shared by every enum with a badge() method
 * (DemandStatus, OrderStatus, MatchStatus, TokenStatus). One place to keep
 * the light/dark pairing consistent instead of four.
 *
 * Color is functional here, not decorative: slate is neutral, sky is
 * in-progress, amber is waiting-on-someone, emerald is settled-good,
 * rose is settled-bad. Nothing outside that vocabulary.
 */
class Tone
{
    private const MAP = [
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-white/5 dark:text-slate-400 dark:ring-white/10',
        'sky' => 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-400/20',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20',
        'emerald' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/20',
        'rose' => 'bg-rose-50 text-rose-800 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20',
    ];

    public static function badge(string $color): string
    {
        return self::MAP[$color] ?? self::MAP['slate'];
    }
}
