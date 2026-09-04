<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Everything a new school needs on its first day.
 *
 * The catalogue seeders are reused rather than duplicated — they are already
 * written to run inside whichever school is active, which is what makes them
 * building blocks the console can call.
 *
 * Deliberately NOT seeded: opening stock balances. A new school's figures are
 * whatever its auditor counts, and inventing them would make the variance
 * report meaningless from the first day — the same reason the existing opening
 * balances carry a warning.
 */
class NewSchoolSeeder extends Seeder
{
    /**
     * @param  bool  $withCatalogue  Copy the standard blocks, categories and
     *                               item codes, or leave the school to build its own.
     *
     *   There is no right answer to this in the abstract, which is exactly why
     *   it is asked rather than assumed. The standard catalogue is Prativa's:
     *   six blocks and 54 codes down to "2024 — 3 Seater Table". For a school
     *   with the same furniture it saves a day of typing; for one with
     *   different buildings entirely it is 54 wrong rows to delete.
     */
    public function forSchool(
        Tenant $tenant,
        string $adminName,
        string $adminEmail,
        bool $withCatalogue = true,
    ): TenantUser {
        // Always: a school with no approval ladder and no petty cash ceiling
        // cannot function at all, and both are editable in Setup.
        $this->call([
            ApprovalTierSeeder::class,
            SettingSeeder::class,
        ]);

        if ($withCatalogue) {
            $this->call([
                LocationSeeder::class,
                CategorySeeder::class,
                ItemTypeSeeder::class,
            ]);
        }

        return $this->firstAdministrator($tenant, $adminName, $adminEmail);
    }

    /**
     * The school's own Super Admin, who creates everybody else. If the email
     * already belongs to somebody — they administer another school — they keep
     * their existing login and simply gain a posting here.
     */
    private function firstAdministrator(Tenant $tenant, string $name, string $email): TenantUser
    {
        $person = User::firstOrCreate(
            ['email' => $email],
            [
                'full_name' => $name,
                'password' => Hash::make(config('prativa.seed_password')),
                'is_active' => true,
                'must_reset_password' => true,
            ],
        );

        $membership = TenantUser::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $person->id],
            [
                'staff_code' => 'ADM-001',
                'designation' => 'Super Administrator',
                // The top band, so there is somebody who can sign for anything
                // until the school sets its own ladder up.
                'approval_tier' => 4,
                'is_active' => true,
            ],
        );

        $membership->syncRoles([Role::SUPER_ADMIN->value, Role::INITIATOR->value]);

        return $membership;
    }
}
