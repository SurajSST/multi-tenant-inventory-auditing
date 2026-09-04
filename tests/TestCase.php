<?php

namespace Tests;

use App\Http\Middleware\ResolveTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * The seeded catalogue, staff and approval ladder are the fixture every
     * test works against — the same data the school starts with.
     */
    protected $seed = true;

    /**
     * The school a test is working in unless it says otherwise.
     *
     * Almost every test here is about one school's controls, not about
     * tenancy, so they are put inside Prativa and read exactly as they did
     * before the system had more than one. TenantIsolationTest is where the
     * second school comes out.
     */
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::where('slug', 'prativa')->firstOrFail();
        $this->actAsSchool($this->tenant);
    }

    /** Put the rest of this test inside one school. */
    protected function actAsSchool(Tenant $tenant): Tenant
    {
        $this->tenant = $tenant;
        app(TenantContext::class)->set($tenant);
        // HTTP requests read the school from the session, the way a signed-in
        // person's browser does.
        session([ResolveTenant::SESSION_KEY => $tenant->id]);

        return $tenant;
    }

    protected function school(string $slug): Tenant
    {
        return Tenant::where('slug', $slug)->firstOrFail();
    }

    /**
     * The seeded staff member with this email, ready to act.
     *
     * Emails are per school — md@prativa.edu.np and md@everest.edu.np are two
     * different people, which is the ordinary case.
     */
    protected function staff(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        $user->forceFill(['must_reset_password' => false])->save();

        return $user;
    }

    /**
     * Signing in also settles which school the request is for, so the tenant
     * middleware has the same thing to read that a real session would.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        return $this->withSession([ResolveTenant::SESSION_KEY => $this->tenant->id]);
    }
}
