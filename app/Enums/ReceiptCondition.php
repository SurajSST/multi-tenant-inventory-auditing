<?php

namespace App\Enums;

enum ReceiptCondition: string
{
    case GOOD = 'GOOD';
    case DAMAGED = 'DAMAGED';
    case WRONG_ITEM = 'WRONG_ITEM';
    case SHORT_SUPPLY = 'SHORT_SUPPLY';

    public function label(): string
    {
        return match ($this) {
            self::GOOD => 'Good — as ordered',
            self::DAMAGED => 'Damaged on arrival',
            self::WRONG_ITEM => 'Wrong item supplied',
            self::SHORT_SUPPLY => 'Short supply',
        };
    }
}
