<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_the_login_screen_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_a_user_requiring_reset_is_forced_to_change_their_password_first(): void
    {
        $user = User::where('email', 'md@prativa.edu.np')->firstOrFail();
        $user->forceFill(['must_reset_password' => true])->save();

        $this->actingAs($user)->get('/')->assertRedirect(route('password.change'));
    }

    public function test_a_seeded_user_can_access_dashboard_directly(): void
    {
        $user = User::where('email', 'md@prativa.edu.np')->firstOrFail();

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Dashboard');
    }
}
