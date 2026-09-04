<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => $validated['password'],
            'must_reset_password' => false,
        ])->save();

        // A password change signs out every other session — if the old one had
        // leaked, the leak ends here.
        Auth::logoutOtherDevices($validated['password']);

        $this->audit->record(
            action: 'PASSWORD_CHANGED',
            entity: 'users',
            entityId: $user->id,
            detail: "{$user->full_name} changed their own password; all other sessions were signed out",
            actor: $user,
        );

        return redirect()->route('dashboard')->with('status', 'Your password has been changed.');
    }
}
