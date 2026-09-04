<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\MatchStatus;
use App\Enums\ReceiptCondition;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Services\BillService;
use App\Services\DemandService;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ThreeWayMatchTest extends TestCase
{
    private function receivedOrder(float $orderAmount = 60000): PurchaseOrder
    {
        $demands = app(DemandService::class);
        $orders = app(OrderService::class);
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $demand = $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Grade 8',
            justification: 'Replacing broken classroom chairs before the new session.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );

        foreach (['hod.science', 'admin.officer', 'md'] as $approver) {
            $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff($approver.'@prativa.edu.np'));
        }

        $order = $orders->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => $orderAmount,
        ], $this->staff('purchase@prativa.edu.np'));

        $orders->receive($order->id, [
            ['demand_line_id' => $demand->lines->first()->id, 'qty_received' => 40],
        ], [
            'location_id' => Location::where('code', 'A')->firstOrFail()->id,
            'condition' => ReceiptCondition::GOOD,
        ], $this->staff('store@prativa.edu.np'));

        return $order->fresh();
    }

    public function test_a_bill_cannot_be_entered_before_the_goods_are_verified(): void
    {
        $demands = app(DemandService::class);
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        $demand = $demands->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 40, 'unit_rate' => 1500]],
            department: 'Grade 8',
            justification: 'Replacing broken classroom chairs before the new session.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );

        foreach (['hod.science', 'admin.officer', 'md'] as $approver) {
            $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff($approver.'@prativa.edu.np'));
        }

        $order = app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 60000,
        ], $this->staff('purchase@prativa.edu.np'));

        $this->expectException(ValidationException::class);

        app(BillService::class)->create([
            'bill_no' => 'HFU/2082/0455',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 60000,
        ], $this->staff('accounts@prativa.edu.np'));
    }

    public function test_a_bill_that_equals_the_order_and_the_approval_matches(): void
    {
        $order = $this->receivedOrder();

        $bill = app(BillService::class)->create([
            'bill_no' => 'HFU/2082/0455',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 60000,
        ], $this->staff('accounts@prativa.edu.np'));

        $this->assertSame(MatchStatus::MATCHED, $bill->match_status);
        $this->assertSame('0.00', (string) $bill->variance_amount);
    }

    public function test_a_bill_above_the_order_is_flagged(): void
    {
        $order = $this->receivedOrder();

        $bill = app(BillService::class)->create([
            'bill_no' => 'HFU/2082/0456',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 63500,
        ], $this->staff('accounts@prativa.edu.np'));

        $this->assertSame(MatchStatus::MISMATCH, $bill->match_status);
        $this->assertSame('3500.00', (string) $bill->variance_amount);
    }

    public function test_a_bill_that_matches_the_order_but_exceeds_the_approval_is_flagged(): void
    {
        // Ordered above the approved amount, then billed at the ordered figure.
        $order = $this->receivedOrder(orderAmount: 65000);

        $bill = app(BillService::class)->create([
            'bill_no' => 'HFU/2082/0457',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 65000,
        ], $this->staff('accounts@prativa.edu.np'));

        $this->assertSame(MatchStatus::MISMATCH, $bill->match_status,
            'Equalling the order is not enough — it must also stay within what was approved.');
    }

    public function test_the_same_bill_number_cannot_be_entered_twice(): void
    {
        $order = $this->receivedOrder();
        $accounts = $this->staff('accounts@prativa.edu.np');
        $bills = app(BillService::class);

        $bills->create([
            'bill_no' => 'HFU/2082/0455',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 60000,
        ], $accounts);

        $this->expectException(ValidationException::class);

        $bills->create([
            'bill_no' => 'HFU/2082/0455',
            'vendor_name' => 'Himalaya Furniture Udyog',
            'bill_date' => now()->toDateString(),
            'bill_amount' => 100,
        ], $accounts);
    }

    public function test_clearing_a_variance_needs_a_written_reason_and_keeps_the_original_figures(): void
    {
        $order = $this->receivedOrder();
        $accounts = $this->staff('accounts@prativa.edu.np');
        $bills = app(BillService::class);

        $bill = $bills->create([
            'bill_no' => 'HFU/2082/0456',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 63500,
        ], $accounts);

        try {
            $bills->clearVariance($bill->id, 'ok', $accounts);
            $this->fail('A cleared variance must carry a written reason.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sentence', $e->validator->errors()->first());
        }

        $cleared = $bills->clearVariance(
            $bill->id,
            'Vendor added transport and loading charges agreed verbally with the purchase officer.',
            $accounts,
        );

        $this->assertSame(MatchStatus::VARIANCE_CLEARED, $cleared->match_status);
        $this->assertSame($accounts->id, $cleared->cleared_by_id);

        // Nothing is erased.
        $this->assertSame('60000.00', (string) $cleared->approved_amount);
        $this->assertSame('60000.00', (string) $cleared->ordered_amount);
        $this->assertSame('63500.00', (string) $cleared->bill_amount);
        $this->assertSame('3500.00', (string) $cleared->variance_amount);
    }

    public function test_the_three_way_view_reports_the_live_figures(): void
    {
        $order = $this->receivedOrder();

        app(BillService::class)->create([
            'bill_no' => 'HFU/2082/0456',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 63500,
        ], $this->staff('accounts@prativa.edu.np'));

        $row = app(BillService::class)->threeWay()->firstWhere('bill_no', 'HFU/2082/0456');

        $this->assertNotNull($row);
        $this->assertSame('60000.00', $row->approved_amount);
        $this->assertSame('60000.00', $row->ordered_amount);
        $this->assertSame('63500.00', $row->billed_amount);
        $this->assertSame('3500.00', $row->variance_vs_order);
    }
}
