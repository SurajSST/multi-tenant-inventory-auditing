<?php

namespace App\Enums;

use App\Support\Tone;

enum TokenStatus: string
{
    case ISSUED = 'ISSUED';
    case WITH_ACCOUNTS = 'WITH_ACCOUNTS';
    case PAID = 'PAID';
    case VOIDED = 'VOIDED';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Generated',
            self::WITH_ACCOUNTS => 'Under Review',
            self::PAID => 'Paid',
            self::VOIDED => 'Voided',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::ISSUED => Tone::badge('sky'),
            self::WITH_ACCOUNTS => Tone::badge('amber'),
            self::PAID => Tone::badge('emerald'),
            self::VOIDED => Tone::badge('slate'),
        };
    }

    /** Tokens that are still money the school owes. */
    public static function open(): array
    {
        return [self::ISSUED, self::WITH_ACCOUNTS];
    }
}
