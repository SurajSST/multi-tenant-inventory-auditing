<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Display the form to request a password reset link.
     */
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send the password reset link to the given user.
     */
    public function email(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $throttleKey = 'forgot-pw|'.strtolower($request->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => 'Too many reset attempts. Please wait '.ceil($seconds / 60).' minute(s) before trying again.',
            ]);
        }

        RateLimiter::hit($throttleKey, 300);

        // Security practice: verify user exists and is active before attempting reset
        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->is_active) {
            // Return success message anyway to prevent user enumeration
            return back()->with('status', 'If an active account exists with that email, a password reset link has been sent.');
        }

        $status = Password::broker()->sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    /**
     * Display the reset password form with the given token.
     */
    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'must_reset_password' => false,
                ])->save();

                $this->audit->record(
                    action: 'PASSWORD_RESET',
                    entity: 'users',
                    entityId: $user->id,
                    detail: "Password was reset via security reset token for {$user->full_name}",
                    actor: $user,
                );
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Your password has been reset successfully. Please sign in with your new password.');
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
