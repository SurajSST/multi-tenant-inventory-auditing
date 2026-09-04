<?php

namespace App\Enums;

use App\Support\Tone;

enum MatchStatus: string
{
    case MATCHED = 'MATCHED';
    case MISMATCH = 'MISMATCH';
    case VARIANCE_CLEARED = 'VARIANCE_CLEARED';

    public function label(): string
    {
        return match ($this) {
            self::MATCHED => 'Matched',
            self::MISMATCH => 'Mismatch',
            self::VARIANCE_CLEARED => 'Variance Cleared',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::MATCHED => Tone::badge('emerald'),
            self::MISMATCH => Tone::badge('rose'),
            self::VARIANCE_CLEARED => Tone::badge('amber'),
        };
    }
}
