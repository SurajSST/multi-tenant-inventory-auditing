<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveTenant;
use App\Livewire\Platform\Schools;
use App\Models\ApprovalTier;
use App\Models\DemandForm;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SettingService;
use App\Tenancy\TenantContext;
use Database\Seeders\TestingDataSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The console that sits above every school — and the guards on the two things
 * it can do that reach outside one: setting a school up, and giving it a logo.
 */
class PlatformConsoleTest extends TestCase
{
    private function owner(): User
    {
        $owner = User::where('email', 'admin@gmail.com')->firstOrFail();
        $owner->forceFill(['must_reset_password' => false])->save();

        return $owner;
    }

    // ── the console has to actually work ─────────────────────

    /**
     * Every button on the console is a Livewire action, and a Livewire action
     * is an XHR to the update endpoint — whose route name is not one of the
     * screen names the tenant middleware knows about.
     *
     * Redirecting that XHR does not send anybody anywhere. Livewire follows the
     * redirect by reloading the page the person is already on, so the button
     * silently does nothing. That is exactly what happened: a platform owner
     * with no school in session had every click on the console bounced back to
     * the console.
     */
    public function test_a_livewire_action_is_not_redirected_away(): void
    {
        $owner = $this->owner();
        session()->forget(ResolveTenant::SESSION_KEY);

        $request = Request::create('/livewire/update', 'POST');
        $request->headers->set('X-Livewire', 'true');
        $request->setUserResolver(fn () => $owner);
        $request->setLaravelSession(session()->driver());

        $reached = false;
        $response = app(ResolveTenant::class)->handle($request, function () use (&$reached) {
            $reached = true;

            return new Response('ok');
        });

        $this->assertTrue($reached, 'The tenant middleware swallowed a Livewire action.');
        $this->assertFalse($response->isRedirect(),
            'A Livewire action was redirected, which makes the button do nothing.');
    }

    /** An ordinary page request, though, still routes them to the console. */
    public function test_a_platform_owner_browsing_without_a_school_still_lands_on_the_console(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->withSession([ResolveTenant::SESSION_KEY => null])
            ->get('/register')
            ->assertRedirect(route('platform.schools'));
    }

    // ── the logo has to belong to this site ──────────────────

    public static function badLogos(): array
    {
        return [
            'protocol relative' => ['//tracker.example.com/pixel.png'],
            'inline data blob' => ['data:image/png;base64,iVBORw0KGgo='],
            'script scheme' => ['javascript:alert(1)'],
            'plain http' => ['http://insecure.example.com/logo.png'],
            'a relative guess' => ['img/logo/school.png'],
        ];
    }

    /**
     * An https address or a path on this site is fine. Everything else is not:
     * a data: blob, a javascript: scheme, a protocol-relative //host/… that
     * silently follows whatever the page was served over, or plain http, which
     * would have staff on an internal network fetching over the clear.
     */
    #[DataProvider('badLogos')]
    public function test_a_logo_that_is_not_a_site_path_or_https_is_refused(string $address): void
    {
        Livewire::actingAs($this->owner())
            ->test(Schools::class)
            ->set('name', 'Himalaya Boarding School')
            ->set('slug', 'himalaya')
            ->set('logoUrl', $address)
            ->set('adminName', 'P. Lama')
            ->set('adminEmail', 'admin@himalaya.edu.np')
            ->call('create')
            ->assertHasErrors('logoUrl');

        $this->assertNull(Tenant::where('slug', 'himalaya')->first());
    }

    public function test_a_logo_path_on_this_site_is_accepted(): void
    {
        Livewire::actingAs($this->owner())
            ->test(Schools::class)
            ->set('name', 'Himalaya Boarding School')
            ->set('slug', 'himalaya')
            ->set('logoUrl', '/img/logo/himalaya.png')
            ->set('adminName', 'P. Lama')
            ->set('adminEmail', 'admin@himalaya.edu.np')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame(
            '/img/logo/himalaya.png',
            Tenant::where('slug', 'himalaya')->firstOrFail()->logo_url,
        );
    }

    /** A school that already keeps its crest on its own site can point at it. */
    public function test_an_https_logo_address_is_accepted(): void
    {
        Livewire::actingAs($this->owner())
            ->test(Schools::class)
            ->set('name', 'Himalaya Boarding School')
            ->set('slug', 'himalaya')
            ->set('logoUrl', 'https://himalaya.edu.np/crest.png')
            ->set('adminName', 'P. Lama')
            ->set('adminEmail', 'admin@himalaya.edu.np')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame(
            'https://himalaya.edu.np/crest.png',
            Tenant::where('slug', 'himalaya')->firstOrFail()->logo_url,
        );
    }

    /**
     * An uploaded logo has to land somewhere the browser can actually reach it.
     * The path only resolves because `php artisan storage:link` has been run —
     * which is why that is now part of `composer setup`.
     */
    public function test_an_uploaded_logo_is_stored_where_the_browser_can_reach_it(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->owner())
            ->test(Schools::class)
            ->set('name', 'Himalaya Boarding School')
            ->set('slug', 'himalaya')
            ->set('logoFile', UploadedFile::fake()->image('crest.png'))
            ->set('adminName', 'P. Lama')
            ->set('adminEmail', 'admin@himalaya.edu.np')
            ->call('create')
            ->assertHasNoErrors();

        $stored = Tenant::where('slug', 'himalaya')->firstOrFail()->logo_url;

        $this->assertStringStartsWith('/storage/logos/', $stored);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $stored));
    }

    public function test_replacing_an_uploaded_logo_does_not_leave_the_old_one_behind(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(Schools::class)
            ->set('name', 'Himalaya Boarding School')
            ->set('slug', 'himalaya')
            ->set('logoFile', UploadedFile::fake()->image('old.png'))
            ->set('adminName', 'P. Lama')
            ->set('adminEmail', 'admin@himalaya.edu.np')
            ->call('create')
            ->assertHasNoErrors();

        $tenant = Tenant::where('slug', 'himalaya')->firstOrFail();
        $first = $tenant->logo_url;

        Livewire::actingAs($owner)
            ->test(Schools::class)
            ->call('editSchool', $tenant->id)
            ->set('logoFile', UploadedFile::fake()->image('new.png'))
            ->call('update')
            ->assertHasNoErrors();

        $second = $tenant->fresh()->logo_url;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing(str_replace('/storage/', '', $first));
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $second));
    }

    // ── what a new school starts with ────────────────────────

    /**
     * The standard catalogue is one school's: six blocks and 54 item codes down
     * to "2024 — 3 Seater Table". Copying it saves a school with the same
     * furniture a day of typing, and gives a school with different buildings 54
     * wrong rows to delete. So it is asked rather than assumed.
     */
    public function test_a_new_school_can_start_with_the_standard_catalogue(): void
    {
        $this->setUpSchool(withCatalogue: true);

        $everest = Tenant::where('slug', 'himalaya')->firstOrFail();

        app(TenantContext::class)->runFor($everest, function () {
            $this->assertGreaterThan(0, Location::count());
            $this->assertGreaterThan(0, ItemType::count());
        });
    }

    public function test_a_new_school_can_start_with_an_empty_register(): void
    {
        $this->setUpSchool(withCatalogue: false);

        $everest = Tenant::where('slug', 'himalaya')->firstOrFail();

        app(TenantContext::class)->runFor($everest, function () {
            $this->assertSame(0, Location::count(), 'An empty school was given another school blocks.');
            $this->assertSame(0, ItemType::count(), 'An empty school was given another school item codes.');

            // But it can still function: a school with no approval ladder and no
            // petty cash ceiling cannot do anything at all.
            $this->assertGreaterThan(0, ApprovalTier::count());
            $this->assertNotNull(app(SettingService::class)->pettyCashCeiling());
        });
    }

    private function setUpSchool(bool $withCatalogue): void
    {
        Livewire::actingAs($this->owner())
            ->test(Schools::class)
            ->set('name', 'Himalaya Boarding School')
            ->set('slug', 'himalaya')
            ->set('adminName', 'P. Lama')
            ->set('adminEmail', 'admin@himalaya.edu.np')
            ->set('withCatalogue', $withCatalogue)
            ->call('create')
            ->assertHasNoErrors();
    }

    // ── demo data must never reach a real install ────────────

    /**
     * TestingDataSeeder fabricates demand forms, orders, a goods receipt, a bill
     * and petty cash tokens. Those go into append-only tables the database
     * refuses UPDATE and DELETE on, so on a live install they could never be
     * removed again. It has to refuse to run anywhere but local.
     */
    public function test_the_demo_seeder_refuses_to_run_on_a_real_install(): void
    {
        $before = DemandForm::count();

        app()->detectEnvironment(fn () => 'production');

        try {
            app(TestingDataSeeder::class)->run();
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }

        $this->assertSame($before, DemandForm::count(),
            'The demo seeder fabricated records on a production environment.');
    }
}
