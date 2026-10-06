<?php

namespace App\Enums;

enum AccountType: string
{
    case ASSET = 'ASSET';
    case LIABILITY = 'LIABILITY';
    case EQUITY = 'EQUITY';
    case REVENUE = 'REVENUE';
    case EXPENSE = 'EXPENSE';

    public function label(): string
    {
        return match ($this) {
            self::ASSET => 'Asset (सम्पत्ति)',
            self::LIABILITY => 'Liability (दायित्व)',
            self::EQUITY => 'Equity (पूँजी कोष)',
            self::REVENUE => 'Revenue (आम्दानी)',
            self::EXPENSE => 'Expense (खर्च)',
        };
    }
}
