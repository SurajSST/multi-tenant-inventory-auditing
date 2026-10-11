<?php

namespace Tests\Feature;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_guest_can_view_forgot_password_page(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Forgot password')
            ->assertSee('Send password reset link');
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();

        $user = $this->staff('p.karki@prativa.edu.np');

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) {
                return ! empty($notification->token);
            }
        );
    }

    public function test_guest_can_view_reset_password_page_with_token(): void
    {
        $this->get(route('password.reset', ['token' => 'sample-test-token', 'email' => 'p.karki@prativa.edu.np']))
            ->assertOk()
            ->assertSee('Reset password')
            ->assertSee('sample-test-token')
            ->assertSee('p.karki@prativa.edu.np');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = $this->staff('p.karki@prativa.edu.np');
        $token = Password::broker()->createToken($user);

        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNewPassword2026!',
            'password_confirmation' => 'BrandNewPassword2026!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('BrandNewPassword2026!', $user->password));
        $this->assertFalse($user->must_reset_password);

        // Verify audit log has the event
        $this->assertDatabaseHas('audit_log', [
            'action' => 'PASSWORD_RESET',
            'entity' => 'users',
            'entity_id' => $user->id,
        ]);
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = $this->staff('p.karki@prativa.edu.np');

        $response = $this->post(route('password.store'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'BrandNewPassword2026!',
            'password_confirmation' => 'BrandNewPassword2026!',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
