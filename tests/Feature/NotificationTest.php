<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\ReceiptCondition;
use App\Livewire\NotificationBell;
use App\Models\DemandForm;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\BillFlagged;
use App\Notifications\DemandAwaitingYou;
use App\Notifications\DemandDecided;
use App\Notifications\GoodsReceived;
use App\Services\BillService;
use App\Services\DemandService;
use App\Services\OrderService;
use App\Tenancy\TenantContext;
use Database\Seeders\NewSchoolSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Telling people that something is waiting for them.
 *
 * The approval ladder only moves when somebody looks at it, so these check the
 * two halves that make that work: the right people are told, and — just as
 * importantly — the wrong people are not. A system that tells everybody
 * everything is one people stop reading.
 */
class NotificationTest extends TestCase
{
    // ── the ladder ───────────────────────────────────────────

    public function test_a_new_demand_tells_whoever_decides_at_the_first_band(): void
    {
        Notification::fake();

        $this->raiseDemand();

        // Tier 1 is the HOD. They are told; nobody at a higher band is, because
        // the form has not reached them yet.
        Notification::assertSentTo(
            $this->staff('hod.science@prativa.edu.np'),
            DemandAwaitingYou::class,
        );

        Notification::assertNotSentTo(
            $this->staff('chairman@prativa.edu.np'),
            DemandAwaitingYou::class,
        );
    }

    public function test_the_person_who_raised_it_is_never_told_it_needs_deciding(): void
    {
        Notification::fake();

        // The MD raises it themselves — and they also sit at tier 3, so without
        // the exclusion they would be told to decide on their own form. The
        // database would refuse that decision, making the alert a lie.
        $this->raiseDemand($this->staff('md@prativa.edu.np'));

        Notification::assertNotSentTo(
            $this->staff('md@prativa.edu.np'),
            DemandAwaitingYou::class,
        );
    }

    public function test_clearing_a_band_tells_the_next_one_rather_than_the_raiser(): void
    {
        $demand = $this->raiseDemand();

        Notification::fake();

        app(DemandService::class)->decide(
            $demand->id, ApprovalAction::APPROVE, $this->staff('hod.science@prativa.edu.np'),
        );

        // It has moved up, not finished: tier 2 hears about it, and the person
        // who raised it has nothing to do yet.
        Notification::assertSentTo(
            $this->staff('admin.officer@prativa.edu.np'),
            DemandAwaitingYou::class,
        );

        Notification::assertNotSentTo(
            $this->staff('p.karki@prativa.edu.np'),
            DemandDecided::class,
        );
    }

    public function test_a_rejection_tells_the_person_who_raised_it_and_says_why(): void
    {
        $demand = $this->raiseDemand();

        Notification::fake();

        app(DemandService::class)->decide(
            $demand->id,
            ApprovalAction::REJECT,
            $this->staff('hod.science@prativa.edu.np'),
            reason: 'The existing chairs were repaired last month.',
        );

        Notification::assertSentTo(
            $this->staff('p.karki@prativa.edu.np'),
            DemandDecided::class,
            function (DemandDecided $note) {
                $this->assertSame('REJECTED', $note->outcome);
                $this->assertStringContainsString('repaired last month', implode(' ', $note->details()));

                return true;
            },
        );
    }

    // ── goods and money ──────────────────────────────────────

    public function test_verifying_a_delivery_tells_whoever_placed_the_order(): void
    {
        $order = $this->approvedOrder();

        Notification::fake();

        app(OrderService::class)->receive(
            $order->id,
            [['demand_line_id' => $order->demand->lines->first()->id, 'qty_received' => 40]],
            ['condition' => ReceiptCondition::GOOD, 'location_id' => Location::first()->id],
            // Never the same person who ordered — the database refuses that.
            $this->staff('store@prativa.edu.np'),
        );

        Notification::assertSentTo(
            $this->staff('purchase@prativa.edu.np'),
            GoodsReceived::class,
        );
    }

    public function test_a_bill_that_matches_tells_nobody(): void
    {
        $order = $this->receivedOrder();

        Notification::fake();

        app(BillService::class)->create([
            'bill_no' => 'MATCHING/001',
            'purchase_order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => $order->order_amount,
        ], $this->staff('accounts@prativa.edu.np'));

        // Nothing is wrong, so nobody is interrupted. This is the test that
        // keeps the bell worth looking at.
        Notification::assertNothingSent();
    }

    public function test_a_bill_that_does_not_match_tells_accounts(): void
    {
        $order = $this->receivedOrder();

        Notification::fake();

        app(BillService::class)->create([
            'bill_no' => 'OVER/001',
            'purchase_order_id' => $order->id,
            'vendor_id' => $order->vendor_id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => bcadd((string) $order->order_amount, '9000', 2),
        ], $this->staff('accounts@prativa.edu.np'));

        // The other Accounts officer hears; the one who entered it does not,
        // because they are looking at it already.
        Notification::assertSentTo($this->staff('accounts2@prativa.edu.np'), BillFlagged::class);
        Notification::assertNotSentTo($this->staff('accounts@prativa.edu.np'), BillFlagged::class);
    }

    // ── the bell ─────────────────────────────────────────────

    public function test_the_bell_shows_what_is_waiting_and_marks_it_read(): void
    {
        $this->raiseDemand();
        $hod = $this->staff('hod.science@prativa.edu.np');

        $this->assertSame(1, $hod->notificationsHere()->whereNull('read_at')->count());

        Livewire::actingAs($hod)
            ->test(NotificationBell::class)
            ->assertSet('open', false)
            ->call('toggle')
            ->assertSee('is waiting for your decision')
            ->call('markAllRead');

        $this->assertSame(0, $hod->notificationsHere()->whereNull('read_at')->count());
    }

    /**
     * Somebody who works at two schools should not see one school's alerts
     * while working in the other: their roles differ, and the link would go
     * somewhere they cannot currently reach.
     */
    public function test_the_bell_only_shows_the_school_you_are_working_in(): void
    {
        $this->raiseDemand();
        $hod = $this->staff('hod.science@prativa.edu.np');

        $this->assertSame(1, $hod->notificationsHere()->count());

        $everest = Tenant::create([
            'name' => 'Everest English Academy', 'slug' => 'everest', 'is_active' => true,
        ]);

        app(TenantContext::class)->runFor($everest, function () use ($everest, $hod) {
            app(NewSchoolSeeder::class)->forSchool($everest, 'E. Sherpa', 'admin@everest.edu.np');

            $this->assertSame(0, $hod->notificationsHere()->count(),
                'One school notifications showed up while working in another.');
        });
    }

    /**
     * The one that matters operationally.
     *
     * phpunit.xml forces QUEUE_CONNECTION=sync, but the application runs on
     * `database`. A queued notification would therefore pass every other test
     * in this file while, in production, only ever becoming a row in `jobs`
     * that nobody runs a worker to drain — the feature silently doing nothing.
     *
     * So this one uses the real driver and insists the bell is written anyway.
     */
    public function test_notifications_arrive_without_a_queue_worker_running(): void
    {
        config(['queue.default' => 'database']);

        $this->raiseDemand();

        $hod = $this->staff('hod.science@prativa.edu.np');

        $this->assertSame(1, $hod->notificationsHere()->count(),
            'Nothing reached the bell — the notification is probably queued, and '.
            'no worker runs on a school server.');

        $this->assertSame(0, \DB::table('jobs')->count(),
            'A notification was left sitting in the queue instead of being delivered.');
    }

    // ── fixtures ─────────────────────────────────────────────

    private function raiseDemand(?User $raisedBy = null): DemandForm
    {
        $item = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        return app(DemandService::class)->create(
            lines: [['item_type_id' => $item->id, 'item_name' => $item->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Grade 8',
            justification: 'Replacing broken classroom chairs before the new session.',
            user: $raisedBy ?? $this->staff('p.karki@prativa.edu.np'),
        );
    }

    /** A demand walked all the way up the ladder and ordered. */
    private function approvedOrder()
    {
        $demand = $this->raiseDemand();
        $service = app(DemandService::class);

        foreach ([
            1 => 'hod.science@prativa.edu.np',
            2 => 'admin.officer@prativa.edu.np',
            3 => 'md@prativa.edu.np',
            4 => 'chairman@prativa.edu.np',
        ] as $email) {
            if ($demand->fresh()->status->value !== 'PENDING') {
                break;
            }

            $service->decide($demand->id, ApprovalAction::APPROVE, $this->staff($email));
        }

        return app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_id' => Vendor::firstOrCreate(['name' => 'Kathmandu Furnishers'])->id,
            'order_amount' => $demand->fresh()->total_amount,
        ], $this->staff('purchase@prativa.edu.np'));
    }

    /** …and delivered, so a bill can be entered against it. */
    private function receivedOrder()
    {
        $order = $this->approvedOrder();

        app(OrderService::class)->receive(
            $order->id,
            [['demand_line_id' => $order->demand->lines->first()->id, 'qty_received' => 40]],
            ['condition' => ReceiptCondition::GOOD, 'location_id' => Location::first()->id],
            $this->staff('store@prativa.edu.np'),
        );

        return $order->fresh();
    }
}
