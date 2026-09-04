<?php

namespace App\Enums;

enum Lifespan: string
{
    /** Lifespan of a year or more: furniture, laptops, equipment. */
    case DURABLE = 'DURABLE';

    /** Under a year: degradable and consumable supplies. */
    case CONSUMABLE = 'CONSUMABLE';

    public function label(): string
    {
        return match ($this) {
            self::DURABLE => 'Durable Asset',
            self::CONSUMABLE => 'Consumable Item',
        };
    }
}
