<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Tenancy\EntersTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * There is no public registration. Accounts are created by the Super Admin,
 * because every action in this system has to be attributable to a named person.
 */
class LoginController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private EntersTenant $tenants,
    ) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.
                    ceil(RateLimiter::availableIn($throttleKey) / 60).' minute(s).',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 300);

            throw ValidationException::withMessages([
                'email' => 'Those details do not match any account.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Speak to the Super Admin.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        // The platform owner administers schools above any single tenant.
        // They land directly on the platform console.
        if ($user->isPlatformOwner()) {
            return redirect()->intended(route('platform.schools'));
        }

        // Somebody posted to one school is put into it here, so the sign-in is
        // on record even when they are sent straight to the change-password
        // screen and never reach the chooser. Anybody posted to more than one
        // picks, and the row is written there instead.
        if ($tenant = $this->tenants->soleTenantFor($user)) {
            $this->tenants->enter($request, $user, $tenant);

            return redirect()->intended(route('dashboard'));
        }

        return redirect()->route('tenant.choose');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->audit->record(
                action: 'SIGNED_OUT',
                entity: 'users',
                entityId: $user->id,
                detail: "{$user->full_name} signed out",
                actor: $user,
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
