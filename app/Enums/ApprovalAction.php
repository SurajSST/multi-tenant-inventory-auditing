<?php

namespace App\Enums;

enum ApprovalAction: string
{
    case APPROVE = 'APPROVE';
    case REJECT = 'REJECT';

    public function label(): string
    {
        return match ($this) {
            self::APPROVE => 'Approved',
            self::REJECT => 'Rejected',
        };
    }
}
