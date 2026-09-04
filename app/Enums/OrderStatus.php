<?php

namespace App\Enums;

use App\Support\Tone;

enum OrderStatus: string
{
    case PLACED = 'PLACED';
    case PART_RECEIVED = 'PART_RECEIVED';
    case RECEIVED = 'RECEIVED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::PLACED => 'Placed',
            self::PART_RECEIVED => 'Partly Received',
            self::RECEIVED => 'Received',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PLACED => Tone::badge('sky'),
            self::PART_RECEIVED => Tone::badge('amber'),
            self::RECEIVED => Tone::badge('emerald'),
            self::CANCELLED => Tone::badge('slate'),
        };
    }
}
