<?php

namespace Tests\Feature;

use App\Models\ItemType;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every screen renders for somebody who is allowed to see it, and is refused
 * for somebody who is not.
 */
class ScreensTest extends TestCase
{
    public static function screensForTheSuperAdmin(): array
    {
        return [
            'dashboard' => ['/'],
            'register' => ['/register'],
            'variance' => ['/variance'],
            'count sheet' => ['/count'],
            'demands' => ['/demands'],
            'new demand' => ['/demands/new'],
            'approval queue' => ['/demands/queue'],
            'orders' => ['/orders'],
            'new order' => ['/orders/new'],
            'bills' => ['/bills'],
            'new bill' => ['/bills/new'],
            'petty cash' => ['/petty-cash'],
            'issue a token' => ['/petty-cash/new'],
            'setup' => ['/setup'],
            'locations' => ['/setup/locations'],
            'categories' => ['/setup/categories'],
            'item types' => ['/setup/items'],
            'approval ladder' => ['/setup/approval-ladder'],
            'staff' => ['/setup/staff'],
            'settings' => ['/setup/settings'],
            'audit trail' => ['/audit-trail'],
        ];
    }

    #[DataProvider('screensForTheSuperAdmin')]
    public function test_a_super_admin_can_open_every_screen(string $url): void
    {
        $this->actingAs($this->staff('md@prativa.edu.np'))->get($url)->assertOk();
    }

    public function test_the_item_specific_screens_render(): void
    {
        $md = $this->staff('md@prativa.edu.np');
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $this->actingAs($md)->get('/register/'.$chair->id.'/units')->assertOk()->assertSee('CHAIR.S.1');
        $this->actingAs($md)->get('/register/'.$chair->id.'/history')->assertOk();
    }

    public function test_a_teacher_cannot_reach_setup(): void
    {
        $this->actingAs($this->staff('p.karki@prativa.edu.np'))->get('/setup')->assertForbidden();
    }

    public function test_a_teacher_cannot_enter_stock_counts(): void
    {
        $this->actingAs($this->staff('p.karki@prativa.edu.np'))->get('/count')->assertForbidden();
    }

    public function test_a_teacher_cannot_reach_the_bill_register(): void
    {
        $this->actingAs($this->staff('p.karki@prativa.edu.np'))->get('/bills')->assertForbidden();
    }

    public function test_a_teacher_cannot_place_an_order(): void
    {
        $this->actingAs($this->staff('p.karki@prativa.edu.np'))->get('/orders/new')->assertForbidden();
    }

    public function test_a_teacher_sees_only_their_own_activity_in_the_audit_trail(): void
    {
        $this->actingAs($this->staff('p.karki@prativa.edu.np'))
            ->get('/audit-trail')
            ->assertOk()
            ->assertSee('My Activity Trail');
    }

    public function test_an_accountant_sees_only_their_own_activity_in_the_audit_trail(): void
    {
        $this->actingAs($this->staff('accounts@prativa.edu.np'))
            ->get('/audit-trail')
            ->assertOk()
            ->assertSee('My Activity Trail');
    }

    public function test_higher_tier_can_see_all_audit_logs(): void
    {
        $this->actingAs($this->staff('md@prativa.edu.np'))
            ->get('/audit-trail')
            ->assertOk()
            ->assertSee('Audit Trail');
    }

    public function test_the_auditor_can_open_the_count_sheet(): void
    {
        $this->actingAs($this->staff('auditor@prativa.edu.np'))->get('/count')->assertOk();
    }

    public function test_a_deactivated_account_is_signed_out_on_its_next_click(): void
    {
        $user = $this->staff('p.karki@prativa.edu.np');

        $this->actingAs($user)->get('/')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));
    }

    public function test_signing_in_records_the_time_and_writes_to_the_trail(): void
    {
        $user = User::where('email', 'md@prativa.edu.np')->firstOrFail();

        // Signing in settles which school as well as who, so the trail row
        // belongs to a school rather than floating above all of them. Somebody
        // posted to a single school is put straight into it and never sees a
        // chooser — the flow is exactly what it was before there was more than
        // one school.
        $this->post('/login', [
            'email' => 'md@prativa.edu.np',
            'password' => config('prativa.seed_password'),
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->assertDatabaseHas('audit_log', [
            'action' => 'SIGNED_IN',
            'actor_id' => $user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->post('/login', [
            'email' => 'md@prativa.edu.np',
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_sign_in_at_all(): void
    {
        User::where('email', 'p.karki@prativa.edu.np')->update(['is_active' => false]);

        $this->post('/login', [
            'email' => 'p.karki@prativa.edu.np',
            'password' => config('prativa.seed_password'),
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
