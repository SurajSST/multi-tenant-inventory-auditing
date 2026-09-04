<?php

namespace App\Enums;

use App\Support\Tone;

enum DemandStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Withdrawn',
        };
    }

    /** Tailwind classes for the status pill. */
    public function badge(): string
    {
        return match ($this) {
            self::DRAFT => Tone::badge('slate'),
            self::PENDING => Tone::badge('amber'),
            self::APPROVED => Tone::badge('emerald'),
            self::REJECTED => Tone::badge('rose'),
            self::CANCELLED => Tone::badge('slate'),
        };
    }
}
