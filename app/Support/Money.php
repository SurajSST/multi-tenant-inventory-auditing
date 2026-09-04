<?php

namespace App\Support;

/**
 * Every money column in this system is decimal(14,2) in NPR, and Eloquent hands
 * those back as strings. Comparing or adding them as floats would eventually
 * lose a paisa somewhere in a three-way match, so all arithmetic goes through
 * bcmath here.
 */
class Money
{
    public const SCALE = 2;

    public static function of(int|float|string|null $v): string
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return '0.00';
        }

        return bcadd((string) $v, '0', self::SCALE);
    }

    public static function add(int|float|string|null $a, int|float|string|null $b): string
    {
        return bcadd(self::of($a), self::of($b), self::SCALE);
    }

    public static function sub(int|float|string|null $a, int|float|string|null $b): string
    {
        return bcsub(self::of($a), self::of($b), self::SCALE);
    }

    public static function mul(int|float|string|null $a, int|float|string|null $b): string
    {
        return bcmul(self::of($a), self::of($b), self::SCALE);
    }

    /** -1, 0 or 1. */
    public static function cmp(int|float|string|null $a, int|float|string|null $b): int
    {
        return bccomp(self::of($a), self::of($b), self::SCALE);
    }

    public static function eq(int|float|string|null $a, int|float|string|null $b): bool
    {
        return self::cmp($a, $b) === 0;
    }

    public static function gt(int|float|string|null $a, int|float|string|null $b): bool
    {
        return self::cmp($a, $b) > 0;
    }

    public static function gte(int|float|string|null $a, int|float|string|null $b): bool
    {
        return self::cmp($a, $b) >= 0;
    }

    public static function lt(int|float|string|null $a, int|float|string|null $b): bool
    {
        return self::cmp($a, $b) < 0;
    }

    public static function lte(int|float|string|null $a, int|float|string|null $b): bool
    {
        return self::cmp($a, $b) <= 0;
    }

    public static function isZero(int|float|string|null $v): bool
    {
        return self::cmp($v, 0) === 0;
    }

    public static function abs(int|float|string|null $v): string
    {
        $v = self::of($v);

        return str_starts_with($v, '-') ? substr($v, 1) : $v;
    }

    /** @param  iterable<int|float|string|null>  $values */
    public static function sum(iterable $values): string
    {
        $total = '0.00';

        foreach ($values as $v) {
            $total = self::add($total, $v);
        }

        return $total;
    }

    /**
     * "Rs. 1,23,456.00" — South Asian digit grouping, the way the school's own
     * paperwork reads.
     */
    public static function npr(int|float|string|null $v): string
    {
        return 'Rs. '.self::format($v);
    }

    /** The number alone, grouped, without the currency prefix. */
    public static function format(int|float|string|null $v): string
    {
        $value = self::of($v);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '-');

        [$whole, $paisa] = array_pad(explode('.', $value), 2, '00');

        // Last three digits, then pairs: 1,23,45,678
        if (strlen($whole) > 3) {
            $tail = substr($whole, -3);
            $head = substr($whole, 0, -3);
            $head = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head);
            $whole = $head.','.$tail;
        }

        return ($negative ? '-' : '').$whole.'.'.$paisa;
    }
}
