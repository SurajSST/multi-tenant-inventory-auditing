<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\DemandStatus;
use App\Enums\ReceiptCondition;
use App\Enums\TokenStatus;
use App\Livewire\Bills;
use App\Livewire\Demands;
use App\Livewire\Inventory;
use App\Livewire\Orders;
use App\Livewire\PettyCash;
use App\Livewire\Setup;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PettyCashToken;
use App\Services\DemandService;
use App\Services\InventoryService;
use App\Services\OrderService;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The screens themselves, driven the way a member of staff drives them.
 */
class LivewireFlowTest extends TestCase
{
    public function test_the_demand_form_shows_the_tier_the_total_will_reach(): void
    {
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        Livewire::actingAs($this->staff('p.karki@prativa.edu.np'))
            ->test(Demands\Create::class)
            ->set('lines.0.item_type_id', $chair->id)
            ->set('lines.0.quantity', '40')
            ->set('lines.0.unit_rate', '1500')
            // Picking a known item fills its name in.
            ->assertSet('lines.0.item_name', $chair->name)
            ->assertSee('60,000.00')
            ->assertSee('Managing Director');
    }

    public function test_a_demand_form_can_be_raised_from_the_screen(): void
    {
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        Livewire::actingAs($this->staff('p.karki@prativa.edu.np'))
            ->test(Demands\Create::class)
            ->set('department', 'Grade 8')
            ->set('justification', 'Replacing broken classroom chairs before the new session begins.')
            ->set('lines.0.item_type_id', $chair->id)
            ->set('lines.0.quantity', '40')
            ->set('lines.0.unit_rate', '1500')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('demand_forms', [
            'department' => 'Grade 8',
            'total_amount' => '60000.00',
            'current_tier' => 1,
            'final_tier' => 3,
        ]);
    }

    public function test_the_demand_form_refuses_a_thin_justification(): void
    {
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        Livewire::actingAs($this->staff('p.karki@prativa.edu.np'))
            ->test(Demands\Create::class)
            ->set('department', 'Grade 8')
            ->set('justification', 'need')
            ->set('lines.0.item_type_id', $chair->id)
            ->set('lines.0.quantity', '2')
            ->set('lines.0.unit_rate', '1500')
            ->call('save')
            ->assertHasErrors('justification');
    }

    public function test_switching_selected_item_updates_name_and_rate(): void
    {
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $table = ItemType::where('code_prefix', '2024.3T')->firstOrFail();

        Livewire::actingAs($this->staff('p.karki@prativa.edu.np'))
            ->test(Demands\Create::class)
            ->set('lines.0.item_type_id', $chair->id)
            ->assertSet('lines.0.item_name', $chair->name)
            ->assertSet('lines.0.unit_rate', (string) $chair->indicative_rate)
            // Switch to table
            ->set('lines.0.item_type_id', $table->id)
            ->assertSet('lines.0.item_name', $table->name)
            ->assertSet('lines.0.unit_rate', (string) $table->indicative_rate);
    }

    public function test_adding_and_removing_lines_maintains_consistent_state(): void
    {
        Livewire::actingAs($this->staff('p.karki@prativa.edu.np'))
            ->test(Demands\Create::class)
            ->call('addLine')
            ->assertCount('lines', 2)
            ->set('lines.1.item_name', 'Custom Whiteboard Marker')
            ->set('lines.1.quantity', '5')
            ->set('lines.1.unit_rate', '200')
            ->call('removeLine', 0)
            ->assertCount('lines', 1)
            ->assertSet('lines.0.item_name', 'Custom Whiteboard Marker');
    }

    public function test_the_approval_queue_approves_and_advances_a_form(): void
    {
        $demand = $this->pendingDemand();

        Livewire::actingAs($this->staff('hod.science@prativa.edu.np'))
            ->test(Demands\Queue::class)
            ->assertSee($demand->ref)
            ->call('open', $demand->id, 'APPROVE')
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertSame(2, $demand->fresh()->current_tier);
    }

    public function test_the_approval_queue_refuses_a_rejection_with_no_reason(): void
    {
        $demand = $this->pendingDemand();

        Livewire::actingAs($this->staff('hod.science@prativa.edu.np'))
            ->test(Demands\Queue::class)
            ->call('open', $demand->id, 'REJECT')
            ->set('reason', 'no')
            ->call('confirm')
            ->assertHasErrors('reason');

        $this->assertSame(DemandStatus::PENDING, $demand->fresh()->status);
    }

    public function test_the_count_sheet_writes_only_the_lines_that_changed(): void
    {
        $inventory = app(InventoryService::class);
        $auditor = $this->staff('auditor@prativa.edu.np');
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $blockA = Location::where('code', 'A')->firstOrFail();

        $before = $inventory->history($chair->id, $blockA->id)->count();

        Livewire::actingAs($auditor)
            ->test(Inventory\CountSheet::class)
            ->set('locationId', $blockA->id)
            ->set('counts.'.$chair->id, '55')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(55, $inventory->currentQuantity($chair->id, $blockA->id));
        $this->assertCount($before + 1, $inventory->history($chair->id, $blockA->id));
    }

    public function test_the_receipt_screen_refuses_the_person_who_placed_the_order(): void
    {
        $order = $this->placedOrder();
        $buyer = $this->staff('purchase@prativa.edu.np');

        Livewire::actingAs($buyer)
            ->test(Orders\Receive::class, ['order' => $order])
            ->assertSee('You placed this order');
    }

    public function test_the_receipt_screen_requires_a_note_when_the_delivery_is_short(): void
    {
        $order = $this->placedOrder();
        $line = $order->demand->lines->first();

        Livewire::actingAs($this->staff('store@prativa.edu.np'))
            ->test(Orders\Receive::class, ['order' => $order])
            ->set('locationId', Location::where('code', 'A')->firstOrFail()->id)
            ->set('received.'.$line->id, '30')
            ->call('save')
            ->assertHasErrors('discrepancyNote');
    }

    public function test_the_receipt_screen_records_a_full_delivery(): void
    {
        $order = $this->placedOrder();
        $line = $order->demand->lines->first();
        $blockA = Location::where('code', 'A')->firstOrFail();
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $before = app(InventoryService::class)->currentQuantity($chair->id, $blockA->id);

        Livewire::actingAs($this->staff('store@prativa.edu.np'))
            ->test(Orders\Receive::class, ['order' => $order])
            ->set('locationId', $blockA->id)
            ->set('received.'.$line->id, '40')
            ->set('challanNo', 'CH-9981')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($before + 40, app(InventoryService::class)->currentQuantity($chair->id, $blockA->id));
    }

    public function test_the_token_screen_blocks_an_amount_over_the_ceiling(): void
    {
        Livewire::actingAs($this->staff('accounts@prativa.edu.np'))
            ->test(PettyCash\Issue::class)
            ->set('amount', '20000')
            ->assertSet('overCeiling', true)
            ->assertSee('above the petty cash ceiling');
    }

    public function test_a_token_can_be_issued_and_settled_by_a_second_person(): void
    {
        Livewire::actingAs($this->staff('accounts@prativa.edu.np'))
            ->test(PettyCash\Issue::class)
            ->set('billNo', 'STN/778')
            ->set('vendorName', 'Nepal Stationers')
            ->set('amount', '3000')
            ->set('claimantName', 'R. Basnet')
            ->set('purpose', 'Exam stationery')
            ->set('billSighted', true)
            ->call('save')
            ->assertHasNoErrors();

        $token = PettyCashToken::where('bill_no', 'STN/778')->firstOrFail();

        // The issuer cannot settle it.
        Livewire::actingAs($this->staff('accounts@prativa.edu.np'))
            ->test(PettyCash\Index::class)
            ->call('pay', $token->id)
            ->assertHasErrors('token');

        Livewire::actingAs($this->staff('accounts2@prativa.edu.np'))
            ->test(PettyCash\Index::class)
            ->call('pay', $token->id)
            ->assertHasNoErrors();

        $this->assertSame(TokenStatus::PAID, $token->fresh()->status);
    }

    public function test_the_bill_screen_previews_whether_a_bill_will_match(): void
    {
        $order = $this->receivedOrder();

        Livewire::actingAs($this->staff('accounts@prativa.edu.np'))
            ->test(Bills\Create::class)
            ->set('purchaseOrderId', $order->id)
            ->assertSet('billAmount', '60000.00')
            ->assertSet('willMatch', true)
            ->set('billAmount', '63500')
            ->assertSet('willMatch', false)
            ->assertSee('flagged');
    }

    public function test_the_approval_ladder_screen_refuses_a_gap(): void
    {
        Livewire::actingAs($this->staff('md@prativa.edu.np'))
            ->test(Setup\ApprovalLadder::class)
            ->set('tiers.0.max_amount', '10000')
            ->call('save')
            ->assertHasErrors('ladder');
    }

    public function test_the_staff_screen_creates_an_account_on_the_default_password(): void
    {
        Livewire::actingAs($this->staff('md@prativa.edu.np'))
            ->test(Setup\Staff::class)
            ->call('newStaff')
            ->set('staffCode', 'PSS-011')
            ->set('fullName', 'J. Tamang')
            ->set('designation', 'Teacher — Grade 9')
            ->set('email', 'j.tamang@prativa.edu.np')
            ->set('roles', ['INITIATOR'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'j.tamang@prativa.edu.np',
            'must_reset_password' => 0,
            'is_active' => 1,
        ]);
    }

    public function test_the_staff_screen_refuses_standing_yourself_down(): void
    {
        $md = $this->staff('md@prativa.edu.np');
        $posting = $md->membershipFor($this->tenant);

        Livewire::actingAs($md)
            ->test(Setup\Staff::class)
            // What is stood down is a posting at this school, not the account —
            // somebody may work at another school under the same login.
            ->call('toggleActive', $posting->id)
            ->assertHasErrors('staff');

        $this->assertTrue($posting->fresh()->is_active);
    }

    // ── fixtures ─────────────────────────────────────────────

    private function pendingDemand()
    {
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        return app(DemandService::class)->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Grade 8',
            justification: 'Replacing broken classroom chairs before the new session.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );
    }

    private function placedOrder()
    {
        $demand = $this->pendingDemand();
        $demands = app(DemandService::class);

        foreach (['hod.science', 'admin.officer', 'md'] as $approver) {
            $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff($approver.'@prativa.edu.np'));
        }

        return app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 60000,
        ], $this->staff('purchase@prativa.edu.np'))->load('demand.lines');
    }

    private function receivedOrder()
    {
        $order = $this->placedOrder();

        app(OrderService::class)->receive($order->id, [
            ['demand_line_id' => $order->demand->lines->first()->id, 'qty_received' => 40],
        ], [
            'location_id' => Location::where('code', 'A')->firstOrFail()->id,
            'condition' => ReceiptCondition::GOOD,
        ], $this->staff('store@prativa.edu.np'));

        return $order->fresh();
    }
}
