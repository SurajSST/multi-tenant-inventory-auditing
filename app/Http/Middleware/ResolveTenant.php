<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which school this request is for, and proves the person is still
 * entitled to it.
 *
 * The check is re-run on every request rather than trusted from the session,
 * for the same reason EnsureActive re-reads is_active: a posting that is ended
 * has to stop working on the next click, not whenever the session happens to
 * expire.
 */
class ResolveTenant
{
    public const SESSION_KEY = 'tenant_id';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = app(TenantContext::class);

        if (! $user) {
            return $next($request);
        }

        // This is the checkpoint that decides whether somebody may still work
        // at this school, so it must never read a cached answer. Clearing at
        // the request boundary makes the first lookup below authoritative —
        // and everything after it in the same request then reuses that.
        $user->forgetMemberships();

        // A Livewire component action (e.g. clicking a button on the platform console)
        // posts to /livewire/update. Keep these moving without redirection.
        // Full page navigations (wire:navigate GET requests) to tenant pages must still
        // be guarded and redirected when no school is active.
        $isLivewireAction = $request->hasHeader('X-Livewire') && ($request->is('livewire/*') || $request->isMethod('POST'));

        $tenantId = $request->session()->get(self::SESSION_KEY);

        if ($tenantId) {
            $membership = $user->membershipFor($tenantId);

            // The platform owner can inspect and manage any school; automatically
            // attach an active Super Admin posting if one does not exist yet.
            if (! $membership && $user->isPlatformOwner()) {
                $membership = TenantUser::firstOrCreate(
                    ['tenant_id' => $tenantId, 'user_id' => $user->id],
                    [
                        'staff_code' => 'SUPER',
                        'designation' => 'Super Admin',
                        'approval_tier' => 0,
                        'is_active' => true,
                    ]
                );
                $membership->syncRoles([Role::SUPER_ADMIN]);
                $user->forgetMemberships();
                $membership = $user->membershipFor($tenantId);
            }

            if ($membership) {
                $tenant = Tenant::find($tenantId);

                if ($tenant?->is_active) {
                    $context->set($tenant);

                    return $next($request);
                }
            }

            // The posting was ended, or the school was suspended, while they
            // were signed in. Drop it and send them back to choose again.
            $request->session()->forget(self::SESSION_KEY);
            $context->forget();

            if ($user->isPlatformOwner()) {
                return redirect()->route('platform.schools');
            }

            return redirect()->route('tenant.choose')->withErrors([
                'tenant' => 'You no longer have access to that school. Pick another.',
            ]);
        }

        // No school chosen yet. The platform owner has their own console;
        // everybody else has to pick one before the app means anything.
        if ($user->isPlatformOwner()) {
            if ($isLivewireAction || $request->routeIs('platform.*', 'tenant.*', 'logout', 'password.*')) {
                return $next($request);
            }

            return redirect()->route('platform.schools');
        }

        if ($isLivewireAction || $request->routeIs('tenant.*', 'logout', 'password.*')) {
            return $next($request);
        }

        return redirect()->route('tenant.choose');
    }
}
