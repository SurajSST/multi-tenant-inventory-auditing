<?php

namespace App\Enums;

enum UnitStatus: string
{
    case ACTIVE = 'ACTIVE';
    case DAMAGED = 'DAMAGED';
    case UNDER_REPAIR = 'UNDER_REPAIR';
    case DISPOSED = 'DISPOSED';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::DAMAGED => 'Damaged',
            self::UNDER_REPAIR => 'Under Repair',
            self::DISPOSED => 'Disposed',
        };
    }
}
