<?php

namespace App\Enums;

enum DemandApprovalMode: string
{
    case SEQUENTIAL = 'sequential';
    case DIRECT = 'direct';
    case FAST_TRACK = 'fast_track';

    public function label(): string
    {
        return match ($this) {
            self::SEQUENTIAL => 'Sequential Ladder (All Tiers)',
            self::DIRECT => 'Direct Threshold Tier (Only Final Decider)',
            self::FAST_TRACK => 'Executive Fast-Track (Ladder with Override)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SEQUENTIAL => 'Demands start at Tier 1 and climb step-by-step through each verifier up to the final tier.',
            self::DIRECT => 'Demands route immediately to the qualifying tier based on amount (e.g. Tier 4 demands go straight to Tier 4).',
            self::FAST_TRACK => 'Demands climb sequentially by default, but any higher tier decider can approve and fast-track immediately.',
        };
    }
}
