<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

class NepaliDate
{
    /** Nepali month names in English and Devanagari */
    public const MONTHS = [
        1 => ['en' => 'Baishakh', 'np' => 'वैशाख'],
        2 => ['en' => 'Jestha', 'np' => 'जेठ'],
        3 => ['en' => 'Ashadh', 'np' => 'असार'],
        4 => ['en' => 'Shrawan', 'np' => 'साउन'],
        5 => ['en' => 'Bhadra', 'np' => 'भदौ'],
        6 => ['en' => 'Ashwin', 'np' => 'असोज'],
        7 => ['en' => 'Kartik', 'np' => 'कात्तिक'],
        8 => ['en' => 'Mangsir', 'np' => 'मंसिर'],
        9 => ['en' => 'Poush', 'np' => 'पुष'],
        10 => ['en' => 'Magh', 'np' => 'माघ'],
        11 => ['en' => 'Falgun', 'np' => 'फागुन'],
        12 => ['en' => 'Chaitra', 'np' => 'चैत'],
    ];

    /**
     * Standard Bikram Sambat calendar month day counts (2070 - 2090 BS).
     * [Baisakh, Jestha, Ashadh, Shrawan, Bhadra, Ashwin, Kartik, Mangsir, Poush, Magh, Falgun, Chaitra]
     */
    private const CALENDAR = [
        2070 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2071 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2072 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2073 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2074 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2075 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2076 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2077 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2078 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2079 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2080 => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30],
        2081 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2082 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31],
        2083 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2084 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2085 => [31, 32, 31, 32, 30, 31, 30, 30, 29, 30, 30, 30],
        2086 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2087 => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30],
        2088 => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30],
        2089 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
        2090 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31],
    ];

    /** Reference AD start date for 2070-01-01 BS (2013-04-14) */
    private const BASE_AD = '2013-04-14';

    private const BASE_BS_YEAR = 2070;

    /**
     * Convert an AD date to Bikram Sambat (BS).
     *
     * @return array{year: int, month: int, day: int, month_name: string, formatted: string, label: string}|null
     */
    public static function fromAd(DateTimeInterface|string|null $adDate): ?array
    {
        if (! $adDate) {
            return null;
        }

        $carbon = $adDate instanceof DateTimeInterface
            ? Carbon::instance($adDate)->startOfDay()
            : Carbon::parse($adDate)->startOfDay();

        $baseDate = Carbon::parse(self::BASE_AD)->startOfDay();
        $daysDiff = $baseDate->diffInDays($carbon, false);

        if ($daysDiff < 0) {
            // Fallback for dates before 2070 BS
            return [
                'year' => $carbon->year + 57,
                'month' => $carbon->month,
                'day' => $carbon->day,
                'month_name' => self::MONTHS[$carbon->month]['en'],
                'formatted' => sprintf('%04d-%02d-%02d', $carbon->year + 57, $carbon->month, $carbon->day),
                'label' => sprintf('%s %d, %d', self::MONTHS[$carbon->month]['en'], $carbon->day, $carbon->year + 57),
            ];
        }

        $currentYear = self::BASE_BS_YEAR;
        $currentMonth = 1;
        $remainingDays = $daysDiff;

        while (isset(self::CALENDAR[$currentYear])) {
            $yearDays = array_sum(self::CALENDAR[$currentYear]);
            if ($remainingDays < $yearDays) {
                break;
            }
            $remainingDays -= $yearDays;
            $currentYear++;
        }

        if (isset(self::CALENDAR[$currentYear])) {
            foreach (self::CALENDAR[$currentYear] as $month => $daysInMonth) {
                if ($remainingDays < $daysInMonth) {
                    $currentMonth = $month + 1;
                    break;
                }
                $remainingDays -= $daysInMonth;
            }
        }

        $bsDay = (int) $remainingDays + 1;
        $monthName = self::MONTHS[$currentMonth]['en'] ?? 'Baishakh';

        return [
            'year' => $currentYear,
            'month' => $currentMonth,
            'day' => $bsDay,
            'month_name' => $monthName,
            'formatted' => sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $bsDay),
            'label' => sprintf('%s %d, %d', $monthName, $bsDay, $currentYear),
        ];
    }

    /** e.g. "Bhadra 21, 2083" */
    public static function format(DateTimeInterface|string|null $adDate): string
    {
        $bs = self::fromAd($adDate);

        return $bs ? $bs['label'] : '';
    }

    /** e.g. "2083-05-21" */
    public static function toYmd(DateTimeInterface|string|null $adDate): string
    {
        $bs = self::fromAd($adDate);

        return $bs ? $bs['formatted'] : '';
    }

    /** Dual display: "2083-05-21 BS (2026-09-06)" */
    public static function dual(DateTimeInterface|string|null $adDate): string
    {
        if (! $adDate) {
            return '';
        }

        $adStr = $adDate instanceof DateTimeInterface
            ? $adDate->format('Y-m-d')
            : substr((string) $adDate, 0, 10);

        $bs = self::fromAd($adDate);

        return $bs ? "{$bs['formatted']} BS ({$adStr})" : $adStr;
    }
}
