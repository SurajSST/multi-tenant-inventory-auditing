<?php

namespace App\Enums;

enum CountSource: string
{
    /** An auditor walked the block and counted. */
    case PHYSICAL_AUDIT = 'PHYSICAL_AUDIT';

    /** Added automatically when goods were verified as received. */
    case GOODS_RECEIPT = 'GOODS_RECEIPT';

    /** Written off, transferred, or corrected. */
    case ADJUSTMENT = 'ADJUSTMENT';

    /** Migrated from the original LIST_OF_INV sheet. */
    case OPENING_BALANCE = 'OPENING_BALANCE';

    public function label(): string
    {
        return match ($this) {
            self::PHYSICAL_AUDIT => 'Physical Audit',
            self::GOODS_RECEIPT => 'Goods Receipt',
            self::ADJUSTMENT => 'Adjustment',
            self::OPENING_BALANCE => 'Opening Balance',
        };
    }
}
