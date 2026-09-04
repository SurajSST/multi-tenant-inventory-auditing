<?php

namespace Database\Seeders;

use App\Models\ApprovalTier;
use Illuminate\Database\Seeder;

class ApprovalTierSeeder extends Seeder
{
    /**
     * The approval ladder, editable later in Setup.
     *
     * Bands must be contiguous and the top one is open-ended. Section 7 of the
     * brief lists the exact rupee sub-brackets as still to be finalised with
     * the school; these are the reference system's figures.
     */
    private const TIERS = [
        [1, 100, 15000, 'Head of Department', false],
        [2, 15001, 50000, 'Administrative Officer', false],
        [3, 50001, 200000, 'Managing Director', false],
        [4, 200001, null, 'Chairman & Committee', true],
    ];

    public function run(): void
    {
        foreach (self::TIERS as [$no, $min, $max, $label, $minute]) {
            ApprovalTier::updateOrCreate(
                ['tier_no' => $no],
                [
                    'min_amount' => $min,
                    'max_amount' => $max,
                    'decider_label' => $label,
                    'requires_minute' => $minute,
                    'is_active' => true,
                ],
            );
        }
    }
}
