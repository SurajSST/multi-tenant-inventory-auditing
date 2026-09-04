<?php

namespace Tests\Feature;

use App\Enums\CountSource;
use App\Enums\Role;
use App\Http\Middleware\ResolveTenant;
use App\Livewire\Platform\Schools;
use App\Livewire\Setup;
use App\Models\Bill;
use App\Models\DemandForm;
use App\Models\DemandLine;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PettyCashToken;
use App\Models\PurchaseOrder;
use App\Models\StockCountEntry;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\Vendor;
use App\Services\BillService;
use App\Services\DemandService;
use App\Services\InventoryService;
use App\Services\PettyCashService;
use App\Services\ReportService;
use App\Support\RefCounter;
use App\Tenancy\TenantContext;
use Database\Seeders\NewSchoolSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Two schools in one database.
 *
 * The premise of this system is that its rules are enforced by the database
 * rather than by remembering to write them in PHP, so these tests do the same
 * thing the separation-of-duties tests do: check the readable behaviour, then
 * bypass the application entirely and check the database still refuses.
 */
class TenantIsolationTest extends TestCase
{
    private Tenant $prativa;

    private Tenant $everest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prativa = $this->school('prativa');
        $this->everest = $this->ensureSecondSchool();
        $this->actAsSchool($this->prativa);
    }

    /**
     * The second school is seeded only in local, so tests make their own.
     * Built through the same seeder the platform console uses, so what is under
     * test here is the path a real new school actually takes.
     */
    private function ensureSecondSchool(): Tenant
    {
        $existing = Tenant::where('slug', 'everest')->first();

        if ($existing) {
            return $existing;
        }

        $everest = Tenant::create([
            'name' => 'Everest English Academy',
            'slug' => 'everest',
            'short_name' => 'Everest',
            'is_active' => true,
        ]);

        app(TenantContext::class)->runFor($everest, function () use ($everest) {
            app(NewSchoolSeeder::class)
                ->forSchool($everest, 'E. Sherpa', 'admin@everest.edu.np');
        });

        return $everest;
    }

    private function inEverest(callable $work): mixed
    {
        return app(TenantContext::class)->runFor($this->everest, $work);
    }

    private function everestAdmin(): User
    {
        return User::where('email', 'admin@everest.edu.np')->firstOrFail();
    }

    // ── what each school can see ─────────────────────────────

    public function test_one_school_never_sees_another_schools_records(): void
    {
        $demand = $this->raiseDemandInPrativa();

        $this->inEverest(function () use ($demand) {
            $this->assertNull(DemandForm::find($demand->id), 'A demand leaked between schools.');
            $this->assertSame(0, DemandForm::count());
            $this->assertSame(0, Bill::count());
            $this->assertSame(0, PettyCashToken::count());
        });

        // And back the other way, so this is isolation rather than an empty school.
        $this->assertSame(1, DemandForm::count());
    }

    public function test_the_stock_register_never_crosses_one_schools_items_with_anothers_blocks(): void
    {
        $inventory = app(InventoryService::class);

        $prativaBlocks = Location::pluck('name')->sort()->values()->all();
        $register = $inventory->register();

        $this->assertSame($prativaBlocks, $register['blocks']->pluck('name')->sort()->values()->all());

        foreach ($register['rows'] as $row) {
            foreach (array_keys($row['by_block']) as $block) {
                $this->assertContains($block, $prativaBlocks,
                    "The register showed block [{$block}], which belongs to another school.");
            }
        }

        // v_stock_register is a CROSS JOIN of item types and blocks. Unscoped it
        // would pair every school's items with every school's blocks, so the row
        // count is the thing that gives it away.
        $rows = DB::table('v_stock_register')->where('tenant_id', $this->prativa->id)->count();
        $items = ItemType::where('is_active', true)->count();
        $blocks = Location::where('is_active', true)->count();

        $this->assertSame($items * $blocks, $rows);
    }

    public function test_the_variance_report_stays_inside_one_school(): void
    {
        $this->inEverest(fn () => $this->assertCount(0, app(InventoryService::class)->variance()));

        $this->assertGreaterThan(0, app(InventoryService::class)->variance()->count());
    }

    public function test_reports_and_dashboards_count_only_their_own_school(): void
    {
        $this->raiseDemandInPrativa();
        $reports = app(ReportService::class);

        $mine = $reports->dashboard();
        $theirs = $this->inEverest(fn () => app(ReportService::class)->dashboard());

        $this->assertGreaterThan(0, $mine['durable_units']);
        $this->assertSame(0, $theirs['durable_units'], 'A new school opened with another school stock.');
        $this->assertSame(0, $theirs['pending_approvals']);
    }

    // ── the same identifiers in two schools ──────────────────

    public function test_both_schools_can_use_the_same_reference_numbers_and_codes(): void
    {
        $mine = $this->raiseDemandInPrativa();

        $theirs = $this->inEverest(function () {
            $item = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

            return app(DemandService::class)->create(
                lines: [['item_type_id' => $item->id, 'item_name' => $item->name, 'quantity' => 40, 'unit_rate' => 1500]],
                department: 'Grade 8',
                justification: 'Replacing broken classroom chairs before the new session.',
                user: $this->everestAdmin(),
            );
        });

        // Each school numbers from 0001, so the references genuinely collide —
        // which is the point: neither school knows the other exists.
        $this->assertSame($mine->ref, $theirs->ref);
        $this->assertNotSame($mine->id, $theirs->id);

        // The same item code and block code exist in both, as separate rows.
        $this->assertSame(2, ItemType::acrossTenants()->where('code_prefix', 'CHAIR.S')->count());
        $this->assertSame(2, Location::acrossTenants()->where('code', 'A')->count());
    }

    public function test_the_same_bill_number_may_exist_in_both_schools(): void
    {
        $accounts = $this->staff('accounts@prativa.edu.np');

        $mine = app(PettyCashService::class)->issue([
            'bill_no' => 'SHARED/001', 'vendor_name' => 'A Vendor', 'amount' => 900,
            'claimant_name' => 'R. Thapa', 'purpose' => 'Sundries', 'bill_sighted' => true,
        ], $accounts);

        $theirs = $this->inEverest(fn () => app(PettyCashService::class)->issue([
            'bill_no' => 'SHARED/001', 'vendor_name' => 'A Vendor', 'amount' => 900,
            'claimant_name' => 'D. Sherpa', 'purpose' => 'Sundries', 'bill_sighted' => true,
        ], $this->everestAdmin()));

        $this->assertSame($mine->bill_no, $theirs->bill_no);

        // …and the double-claim block still bites WITHIN a school.
        $this->expectException(ValidationException::class);
        app(BillService::class)->create([
            'bill_no' => 'SHARED/001', 'vendor_name' => 'A Vendor',
            'bill_date' => now()->toDateString(), 'bill_amount' => 900,
        ], $accounts);
    }

    public function test_reference_counters_run_separately_per_school(): void
    {
        $first = RefCounter::next('DF', null, $this->prativa->id);
        $second = RefCounter::next('DF', null, $this->prativa->id);
        $other = RefCounter::next('DF', null, $this->everest->id);

        $this->assertNotSame($first['ref'], $second['ref']);
        $this->assertSame($first['ref'], $other['ref'], 'Each school should number from 0001.');
    }

    // ── what the database refuses, service or no service ─────

    public function test_a_demand_line_cannot_point_at_another_schools_demand(): void
    {
        $demand = $this->raiseDemandInPrativa();

        $this->expectException(QueryException::class);

        $this->inEverest(fn () => DemandLine::create([
            'tenant_id' => $this->everest->id,
            'demand_id' => $demand->id,          // Prativa's
            'item_name' => 'Smuggled line',
            'quantity' => 1,
            'unit_rate' => 100,
            'line_total' => 100,
        ]));
    }

    public function test_an_order_cannot_point_at_another_schools_demand(): void
    {
        $demand = $this->raiseDemandInPrativa();

        $this->expectException(QueryException::class);

        $this->inEverest(function () use ($demand) {
            PurchaseOrder::create([
                'tenant_id' => $this->everest->id,
                'ref' => 'PO-9999-0001',
                'fiscal_year' => $demand->fiscal_year,
                'demand_id' => $demand->id,      // Prativa's
                'vendor_id' => Vendor::create(['name' => 'Anywhere Traders'])->id,
                'order_amount' => 1000,
                'ordered_by_id' => $this->everestAdmin()->id,
            ]);
        });
    }

    public function test_a_count_cannot_be_credited_to_somebody_who_does_not_work_there(): void
    {
        $outsider = $this->staff('auditor@prativa.edu.np');

        $this->expectException(QueryException::class);

        $this->inEverest(fn () => StockCountEntry::create([
            'tenant_id' => $this->everest->id,
            'item_type_id' => ItemType::first()->id,
            'location_id' => Location::first()->id,
            'quantity' => 5,
            'previous_qty' => 0,
            'source' => CountSource::PHYSICAL_AUDIT,
            'counted_by_id' => $outsider->id,    // works at Prativa, not here
        ]));
    }

    public function test_a_query_with_no_school_active_fails_rather_than_returning_everything(): void
    {
        app(TenantContext::class)->forget();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no school active');

        DemandForm::count();
    }

    // ── people, postings and switching ───────────────────────

    public function test_one_person_can_hold_a_different_job_at_each_school(): void
    {
        $person = $this->staff('accounts@prativa.edu.np');

        $this->inEverest(fn () => TenantUser::create([
            'tenant_id' => $this->everest->id,
            'user_id' => $person->id,
            'staff_code' => 'EEA-014',
            'designation' => 'Stock Auditor',
            'approval_tier' => 0,
        ])->syncRoles([Role::AUDITOR->value]));

        // At Prativa they are Accounts, and cannot enter counts.
        $this->assertTrue($person->hasRole(Role::ACCOUNTS));
        $this->assertFalse($person->hasRole(Role::AUDITOR));
        $this->assertSame('Accounts Officer', $person->designation);

        // At Everest, the same login is an auditor and nothing else.
        $this->inEverest(function () use ($person) {
            $person->unsetRelation('currentMembership');
            $this->assertTrue($person->hasRole(Role::AUDITOR));
            $this->assertFalse($person->hasRole(Role::ACCOUNTS));
            $this->assertSame('Stock Auditor', $person->designation);
            $this->assertSame('EEA-014', $person->staff_code);
        });
    }

    public function test_a_super_admin_cannot_reach_another_schools_records(): void
    {
        $demand = $this->inEverest(function () {
            $item = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

            return app(DemandService::class)->create(
                lines: [['item_type_id' => $item->id, 'item_name' => $item->name, 'quantity' => 40, 'unit_rate' => 1500]],
                department: 'Grade 8',
                justification: 'Replacing broken classroom chairs before the new session.',
                user: $this->everestAdmin(),
            );
        });

        $this->actingAs($this->staff('md@prativa.edu.np'))
            ->get('/demands/'.$demand->id)
            ->assertNotFound();
    }

    public function test_losing_a_posting_drops_the_school_on_the_next_request(): void
    {
        $person = $this->staff('purchase@prativa.edu.np');

        $this->actingAs($person)->get('/orders')->assertOk();

        $person->membershipFor($this->prativa)->update(['is_active' => false]);

        $this->actingAs($person)->get('/orders')->assertRedirect(route('tenant.choose'));
    }

    public function test_a_suspended_school_locks_its_staff_out(): void
    {
        $person = $this->staff('md@prativa.edu.np');
        $this->prativa->update(['is_active' => false]);

        $this->actingAs($person)->get('/')->assertRedirect(route('tenant.choose'));
    }

    /**
     * The staff screen takes a posting id straight from the browser.
     *
     * A school's own Super Admin passes the route middleware legitimately, and
     * Livewire's checksum covers the component's state rather than the
     * arguments of a method call — so nothing but an explicit tenant predicate
     * stops them handing it another school's id. Resetting a password there
     * would lock that person out of every school, since the login is global.
     */
    public function test_a_super_admin_cannot_touch_another_schools_staff(): void
    {
        $outsider = $this->everestAdmin();
        $theirPosting = $outsider->membershipFor($this->everest);
        $passwordBefore = $outsider->password;

        $ours = Livewire::actingAs($this->staff('md@prativa.edu.np'))->test(Setup\Staff::class);

        foreach (['edit', 'toggleActive', 'resetPassword'] as $method) {
            try {
                $ours->call($method, $theirPosting->id);
                $this->fail("Setup\Staff::{$method}() accepted another school posting id.");
            } catch (ModelNotFoundException) {
                // Correct: outside this school, that posting does not exist.
            }
        }

        $this->assertSame($passwordBefore, $outsider->fresh()->password);
        $this->assertTrue($theirPosting->fresh()->is_active);
    }

    // ── the platform owner ───────────────────────────────────

    public function test_the_platform_console_is_refused_to_a_schools_own_super_admin(): void
    {
        $this->actingAs($this->staff('md@prativa.edu.np'))
            ->get('/platform')
            ->assertForbidden();
    }

    public function test_the_platform_owner_sees_every_school(): void
    {
        $owner = User::where('email', 'admin@gmail.com')->firstOrFail();
        $owner->forceFill(['must_reset_password' => false])->save();

        $this->actingAs($owner)
            ->get('/platform')
            ->assertOk()
            ->assertSee('Prativa Secondary School')
            ->assertSee('Everest English Academy');
    }

    public function test_platform_owner_can_edit_school_and_logo(): void
    {
        $owner = User::where('email', 'admin@gmail.com')->firstOrFail();
        $owner->forceFill(['must_reset_password' => false])->save();

        $prativa = Tenant::where('slug', 'prativa')->firstOrFail();

        Livewire::actingAs($owner)
            ->test(Schools::class)
            ->call('editSchool', $prativa->id)
            ->assertSet('name', 'Prativa Secondary School')
            ->set('shortName', 'Prativa Academy')
            ->set('logoUrl', 'https://example.com/prativa-logo.png')
            ->call('update')
            ->assertHasNoErrors();

        $prativa->refresh();
        $this->assertSame('Prativa Academy', $prativa->short_name);
        $this->assertSame('https://example.com/prativa-logo.png', $prativa->logo_url);
        $this->assertSame('https://example.com/prativa-logo.png', $prativa->logoUrl());
    }

    public function test_platform_owner_login_redirects_directly_to_platform(): void
    {
        $this->post('/login', [
            'email' => 'admin@gmail.com',
            'password' => 'admin123',
        ])->assertRedirect('/platform');
    }

    public function test_platform_owner_without_active_tenant_accessing_dashboard_redirects_to_platform(): void
    {
        $owner = User::where('email', 'admin@gmail.com')->firstOrFail();
        $owner->forceFill(['must_reset_password' => false])->save();

        app(TenantContext::class)->forget();
        session()->forget(ResolveTenant::SESSION_KEY);

        $this->actingAs($owner)
            ->withSession([ResolveTenant::SESSION_KEY => null])
            ->get('/')
            ->assertRedirect('/platform');
    }

    public function test_platform_owner_can_exit_school_back_to_platform(): void
    {
        $owner = User::where('email', 'admin@gmail.com')->firstOrFail();
        $owner->forceFill(['must_reset_password' => false])->save();
        $prativa = Tenant::where('slug', 'prativa')->firstOrFail();

        $this->actingAs($owner)
            ->withSession([ResolveTenant::SESSION_KEY => $prativa->id])
            ->post('/platform/exit')
            ->assertRedirect('/platform')
            ->assertSessionMissing(ResolveTenant::SESSION_KEY);
    }

    public function test_petty_cash_monthly_spend_calculation(): void
    {
        $prativa = Tenant::where('slug', 'prativa')->firstOrFail();

        app(TenantContext::class)->runFor($prativa, function () {
            $service = app(PettyCashService::class);
            $spend = $service->monthlySpend();

            $this->assertCount(6, $spend);
            $this->assertTrue($spend->last()['label'] === now()->format('M'));
        });
    }

    // ── fixture ──────────────────────────────────────────────

    private function raiseDemandInPrativa(): DemandForm
    {
        $item = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        return app(DemandService::class)->create(
            lines: [['item_type_id' => $item->id, 'item_name' => $item->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Grade 8',
            justification: 'Replacing broken classroom chairs before the new session.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );
    }
}
