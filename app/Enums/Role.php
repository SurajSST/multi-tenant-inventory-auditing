<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case CHAIRMAN = 'CHAIRMAN';
    case APPROVER = 'APPROVER';
    case INITIATOR = 'INITIATOR';
    case PURCHASE_OFFICER = 'PURCHASE_OFFICER';
    case RECEIVING_OFFICER = 'RECEIVING_OFFICER';
    case ACCOUNTS = 'ACCOUNTS';
    case AUDITOR = 'AUDITOR';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::CHAIRMAN => 'Chairman & Committee',
            self::APPROVER => 'Tier Approver',
            self::INITIATOR => 'Demand Initiator',
            self::PURCHASE_OFFICER => 'Purchase Officer',
            self::RECEIVING_OFFICER => 'Receiving Officer',
            self::ACCOUNTS => 'Accounts',
            self::AUDITOR => 'Stock Auditor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Full control: staff, categories, blocks, approval ladder, settings.',
            self::CHAIRMAN => 'Decides the top band, with a committee minute reference.',
            self::APPROVER => 'Approves or rejects demand forms within their assigned tier.',
            self::INITIATOR => 'Raises demand forms.',
            self::PURCHASE_OFFICER => 'Places the order with the vendor once a demand is approved.',
            self::RECEIVING_OFFICER => 'Verifies goods on arrival. Never the person who ordered them.',
            self::ACCOUNTS => 'Enters bills, clears variances, issues and settles petty cash tokens.',
            self::AUDITOR => 'The only role that may enter physical stock counts.',
        };
    }

    /** Roles that see every demand, order and bill rather than only their own. */
    public static function seesEverything(): array
    {
        return [self::SUPER_ADMIN, self::ACCOUNTS, self::CHAIRMAN, self::PURCHASE_OFFICER];
    }
}
