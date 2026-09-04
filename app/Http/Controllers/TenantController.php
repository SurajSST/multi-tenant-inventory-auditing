<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveTenant;
use App\Models\Tenant;
use App\Tenancy\EntersTenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Choosing, and changing, which school you are working in.
 *
 * Most people hold one posting and never see any of this — they are put into
 * their school on sign-in and the app looks exactly as it did before there was
 * more than one. It matters for the person who genuinely works at two.
 */
class TenantController extends Controller
{
    public function __construct(private EntersTenant $tenants) {}

    /** The chooser, shown only when there is something to choose. */
    public function choose(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $memberships = $user->activeMemberships();

        if ($memberships->isEmpty()) {
            if ($user->isPlatformOwner()) {
                return redirect()->route('platform.schools');
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is not attached to any school. Speak to your administrator.',
            ]);
        }

        if ($memberships->count() === 1) {
            return $this->enter($request, $memberships->first()->tenant, 'started work in');
        }

        return view('auth.choose-school', ['memberships' => $memberships]);
    }

    /** Switching schools mid-session. */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate(['tenant_id' => ['required', 'string']]);

        $membership = $request->user()->membershipFor($validated['tenant_id']);

        if (! $membership) {
            return back()->withErrors(['tenant' => 'You do not work at that school.']);
        }

        $tenant = Tenant::active()->find($validated['tenant_id']);

        if (! $tenant) {
            return back()->withErrors(['tenant' => 'That school is not active.']);
        }

        return $this->enter($request, $tenant, 'switched to');
    }

    /**
     * Put somebody into a school and record it there. The row is written in the
     * school being entered, so each school's trail shows who worked in it.
     */
    private function enter(Request $request, Tenant $tenant, string $why): RedirectResponse
    {
        $this->tenants->enter($request, $request->user(), $tenant, $why);

        return redirect()->intended(route('dashboard'));
    }

    /** Exit active school back to the platform console (Platform Owner only). */
    public function exitToPlatform(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isPlatformOwner(), 403);

        $request->session()->forget(ResolveTenant::SESSION_KEY);
        app(TenantContext::class)->forget();

        return redirect()->route('platform.schools');
    }
}
