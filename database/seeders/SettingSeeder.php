<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Services\SettingService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            SettingService::PETTY_CASH_CEILING => 15000,
            // The school being seeded, not the one in config — that value is
            // only a fallback for a single-school installation.
            SettingService::SCHOOL_NAME => app(TenantContext::class)->current()?->name
                ?? config('prativa.school_name'),
            // A purchase officer may order above the approved amount, but it is
            // flagged in the audit trail and the bill will not match.
            SettingService::ALLOW_ORDER_ABOVE_APPROVAL => true,
        ];

        foreach ($defaults as $key => $value) {
            AppSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
