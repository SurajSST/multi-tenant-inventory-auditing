<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case CHEQUE = 'CHEQUE';
    case CASH = 'CASH';

    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Bank Transfer (बैंक दाखिला)',
            self::CHEQUE => 'Cheque (चेक)',
            self::CASH => 'Cash (नगद)',
        };
    }
}
