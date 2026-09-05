<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Staff accounts, in two halves.
 *
 * The identity (name, email, password) is global — one row per person, however
 * many schools they work at. The posting (staff code, designation, approval
 * tier, roles, auditor block scope) belongs to one school.
 *
 * Email addresses are prefixed per school so the seeded staff at two different
 * schools are genuinely different people, which is the ordinary case. The one
 * account that deliberately spans schools is the platform owner.
 */
class UserSeeder extends Seeder
{
    /** staff code, name, designation, email local part, roles, approval tier */
    private const STAFF = [
        ['PSS-001', 'S. Sharma', 'Managing Director', 'md', ['SUPER_ADMIN', 'APPROVER', 'INITIATOR'], 3],
        ['PSS-002', 'R. Gurung', 'Chairman', 'chairman', ['CHAIRMAN'], 4],
        ['PSS-003', 'B. Thapa', 'Administrative Officer', 'admin.officer', ['APPROVER', 'INITIATOR'], 2],
        ['PSS-004', 'M. Adhikari', 'Head of Department — Science', 'hod.science', ['APPROVER', 'INITIATOR'], 1],
        ['PSS-005', 'K. Poudel', 'Store / Purchase Officer', 'purchase', ['PURCHASE_OFFICER', 'INITIATOR'], 0],
        ['PSS-006', 'S. Lama', 'Store Keeper (Receiving)', 'store', ['RECEIVING_OFFICER', 'INITIATOR'], 0],
        ['PSS-007', 'A. Shrestha', 'Accounts Officer', 'accounts', ['ACCOUNTS', 'INITIATOR'], 0],
        ['PSS-008', 'N. Rai', 'Accounts Assistant', 'accounts2', ['ACCOUNTS', 'INITIATOR'], 0],
        ['PSS-009', 'D. Bhattarai', 'Assigned Stock Auditor', 'auditor', ['AUDITOR', 'INITIATOR'], 0],
        ['PSS-010', 'P. Karki', 'Teacher — Grade 8', 'p.karki', ['INITIATOR'], 0],
    ];

    public function run(): void
    {
        $tenant = app(TenantContext::class)->current();
        $password = Hash::make(config('prativa.seed_password'));
        $domain = $this->domainFor($tenant);

        foreach (self::STAFF as [$code, $name, $designation, $localPart, $roles, $tier]) {
            $user = User::updateOrCreate(
                ['email' => "{$localPart}@{$domain}"],
                [
                    'full_name' => $name,
                    'password' => $password,
                    'is_active' => true,
                    'must_reset_password' => false,
                ],
            );

            $membership = $this->post($user, $tenant, $code, $designation, $tier);
            $membership->syncRoles($roles);

            // The seeded auditor counts every block at their own school.
            if (in_array(Role::AUDITOR->value, $roles, true)) {
                $this->assignEveryBlock($membership);
            }
        }
    }

    /**
     * Give the platform owner a real posting at this school, so that anything
     * they do inside it is attributed the way everybody else's work is — and so
     * the separation-of-duties rules still bite on them.
     */
    public function attachOwner(User $owner, Tenant $tenant): TenantUser
    {
        $membership = $this->post($owner, $tenant, 'PSS-ADMIN', 'Super Administrator', 4);

        $membership->syncRoles(array_map(fn (Role $r) => $r->value, Role::cases()));
        $this->assignEveryBlock($membership);

        return $membership;
    }

    private function post(User $user, Tenant $tenant, string $code, string $designation, int $tier): TenantUser
    {
        return TenantUser::updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $user->id],
            [
                'staff_code' => $code,
                'designation' => $designation,
                'approval_tier' => $tier,
                'is_active' => true,
            ],
        );
    }

    private function assignEveryBlock(TenantUser $membership): void
    {
        $membership->auditScopes()->delete();

        foreach (Location::pluck('id') as $locationId) {
            $membership->auditScopes()->create([
                'tenant_id' => $membership->tenant_id,
                'location_id' => $locationId,
            ]);
        }
    }

    /** prativa.edu.np for the original school, everest.edu.np for the second. */
    private function domainFor(?Tenant $tenant): string
    {
        return ($tenant?->slug ?? 'prativa').'.edu.np';
    }
}
