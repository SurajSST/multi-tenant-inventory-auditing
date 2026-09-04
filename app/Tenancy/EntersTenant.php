<?php

namespace App\Tenancy;

use App\Http\Middleware\ResolveTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

/**
 * Putting somebody into a school, and recording that they are there.
 *
 * Shared by signing in and by switching, because the two have to agree: a
 * sign-in has to be recorded even when the person is bounced straight to the
 * change-password screen, and the row has to belong to the school they are
 * entering rather than floating above all of them.
 */
class EntersTenant
{
    public function __construct(
        private TenantContext $context,
        private AuditLogger $audit,
    ) {}

    public function enter(Request $request, User $user, Tenant $tenant, string $why = 'started work in'): void
    {
        $request->session()->put(ResolveTenant::SESSION_KEY, $tenant->id);
        $this->context->set($tenant);

        // Roles, staff code and approval tier are all per school. Anything
        // resolved before the switch describes the school being left.
        $user->forgetMemberships();

        $this->audit->record(
            action: $why === 'switched to' ? 'TENANT_SWITCHED' : 'SIGNED_IN',
            entity: 'users',
            entityId: $user->id,
            detail: "{$user->full_name} {$why} {$tenant->name}",
            actor: $user,
        );
    }

    /**
     * The school to drop somebody into without asking, if there is exactly one.
     * Somebody posted to a single school should never see a chooser.
     */
    public function soleTenantFor(User $user): ?Tenant
    {
        $memberships = $user->activeMemberships();

        return $memberships->count() === 1
            ? $memberships->first()->tenant
            : null;
    }
}
