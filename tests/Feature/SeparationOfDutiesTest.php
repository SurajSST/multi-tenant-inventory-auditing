<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\OrderStatus;
use App\Enums\ReceiptCondition;
use App\Models\DemandForm;
use App\Models\ItemType;
use App\Models\Location;
use App\Services\DemandService;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The point of this system is that no single person can move money or stock
 * alone. These tests cover both halves of that: the readable refusal from the
 * service layer, and the database constraint underneath it that holds even when
 * the service is bypassed entirely.
 */
class SeparationOfDutiesTest extends TestCase
{
    private function approvedDemand(): DemandForm
    {
        $demands = app(DemandService::class);
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $demand = $demands->create(
            lines: [[
                'item_type_id' => $chair->id,
                'item_name' => $chair->name,
                'quantity' => 40,
                'unit_rate' => 1500,
            ]],
            department: 'Grade 8',
            justification: 'Replacing broken classroom chairs before the new session.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );

        foreach (['hod.science', 'admin.officer', 'md'] as $approver) {
            $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff($approver.'@prativa.edu.np'));
        }

        return $demand->fresh();
    }

    public function test_the_person_who_raised_a_form_cannot_decide_on_it(): void
    {
        $teacher = $this->staff('p.karki@prativa.edu.np');
        $demands = app(DemandService::class);
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $demand = $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 2, 'unit_rate' => 1500]],
            department: 'Grade 8',
            justification: 'Two replacement chairs for the classroom.',
            user: $teacher,
        );

        // The teacher is not an approver at all, but even an approver who raised
        // the form is refused — this covers the rule itself.
        $md = $this->staff('md@prativa.edu.np');

        $own = $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 2, 'unit_rate' => 1500]],
            department: 'Administration',
            justification: 'Two chairs for the front office.',
            user: $md,
        );

        $this->expectException(AuthorizationException::class);
        $demands->decide($own->id, ApprovalAction::APPROVE, $md);
    }

    public function test_the_database_refuses_a_self_approval_even_without_the_service(): void
    {
        $md = $this->staff('md@prativa.edu.np');
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $demand = app(DemandService::class)->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 2, 'unit_rate' => 1500]],
            department: 'Administration',
            justification: 'Two chairs for the front office.',
            user: $md,
        );

        $this->expectException(QueryException::class);

        DB::table('demand_approvals')->insert([
            'id' => (string) Str::uuid(),
            'demand_id' => $demand->id,
            'tier_no' => 1,
            'actor_id' => $md->id,
            'action' => 'APPROVE',
        ]);
    }

    public function test_an_approver_cannot_decide_at_the_wrong_tier(): void
    {
        $demands = app(DemandService::class);
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $demand = $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Grade 8',
            justification: 'Replacing broken classroom chairs before the new session.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );

        // It sits at tier 1; the Managing Director decides at tier 3.
        $this->expectException(AuthorizationException::class);
        $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff('md@prativa.edu.np'));
    }

    public function test_the_buyer_cannot_verify_their_own_order(): void
    {
        $demand = $this->approvedDemand();
        $buyer = $this->staff('purchase@prativa.edu.np');
        $orders = app(OrderService::class);

        $order = $orders->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 60000,
        ], $buyer);

        $this->expectException(AuthorizationException::class);

        $orders->receive($order->id, [
            ['demand_line_id' => $demand->lines->first()->id, 'qty_received' => 40],
        ], [
            'location_id' => Location::where('code', 'A')->firstOrFail()->id,
            'condition' => ReceiptCondition::GOOD,
        ], $buyer);
    }

    public function test_the_database_refuses_a_self_receipt_even_without_the_service(): void
    {
        $demand = $this->approvedDemand();
        $buyer = $this->staff('purchase@prativa.edu.np');

        $order = app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 60000,
        ], $buyer);

        $this->expectException(QueryException::class);

        DB::table('goods_receipts')->insert([
            'id' => (string) Str::uuid(),
            'purchase_order_id' => $order->id,
            'ordered_by_id' => $order->ordered_by_id,
            'received_by_id' => $buyer->id,
            'location_id' => Location::where('code', 'A')->firstOrFail()->id,
            'condition' => 'GOOD',
        ]);
    }

    public function test_a_different_person_can_verify_and_the_stock_posts_into_the_ledger(): void
    {
        $demand = $this->approvedDemand();
        $buyer = $this->staff('purchase@prativa.edu.np');
        $store = $this->staff('store@prativa.edu.np');
        $orders = app(OrderService::class);
        $inventory = app(InventoryService::class);

        $order = $orders->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 60000,
        ], $buyer);

        $blockA = Location::where('code', 'A')->firstOrFail();
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();
        $before = $inventory->currentQuantity($chair->id, $blockA->id);

        $result = $orders->receive($order->id, [
            ['demand_line_id' => $demand->lines->first()->id, 'qty_received' => 40],
        ], [
            'location_id' => $blockA->id,
            'condition' => ReceiptCondition::GOOD,
        ], $store);

        $this->assertSame(40, $result['units_posted']);
        $this->assertFalse($result['partial']);
        $this->assertSame($before + 40, $inventory->currentQuantity($chair->id, $blockA->id));
    }

    public function test_a_short_delivery_marks_the_order_part_received(): void
    {
        $demand = $this->approvedDemand();
        $orders = app(OrderService::class);

        $order = $orders->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 60000,
        ], $this->staff('purchase@prativa.edu.np'));

        $result = $orders->receive($order->id, [
            ['demand_line_id' => $demand->lines->first()->id, 'qty_received' => 30],
        ], [
            'location_id' => Location::where('code', 'A')->firstOrFail()->id,
            'condition' => ReceiptCondition::SHORT_SUPPLY,
            'discrepancy_note' => 'Ten chairs still with the vendor; promised next week.',
        ], $this->staff('store@prativa.edu.np'));

        $this->assertTrue($result['partial']);
        $this->assertSame(OrderStatus::PART_RECEIVED, $order->fresh()->status);
    }
}
