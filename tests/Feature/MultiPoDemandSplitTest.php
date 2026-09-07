<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\DemandStatus;
use App\Models\DemandForm;
use App\Models\Vendor;
use App\Services\DemandService;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiPoDemandSplitTest extends TestCase
{
    private function approveFully(DemandForm $demand): void
    {
        $demands = app(DemandService::class);
        while ($demand->status === DemandStatus::PENDING) {
            $approver = match ($demand->current_tier) {
                1 => 'hod.science@prativa.edu.np',
                2 => 'admin.officer@prativa.edu.np',
                3 => 'md@prativa.edu.np',
                4 => 'chairman@prativa.edu.np',
            };
            $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff($approver), null, 'MIN-2026-001');
            $demand->refresh();
        }
    }

    public function test_approved_demand_can_be_split_across_multiple_purchase_orders_to_different_vendors(): void
    {
        $initiator = $this->staff('p.karki@prativa.edu.np');
        $buyer = $this->staff('purchase@prativa.edu.np');

        // 1. Create a demand with 100 chairs and 50 desks
        $demand = app(DemandService::class)->create(
            lines: [
                ['item_name' => 'Lab Chair', 'quantity' => 100, 'unit_rate' => 1000.00],
                ['item_name' => 'Lab Desk', 'quantity' => 50, 'unit_rate' => 3000.00],
            ],
            department: 'Science Lab',
            justification: 'Lab furniture upgrade',
            user: $initiator,
            needByDate: now()->addWeeks(2)->toDateString(),
        );

        // 2. Approve the demand fully through ladder
        $this->approveFully($demand);
        $this->assertEquals(DemandStatus::APPROVED, $demand->status);

        $chairLine = $demand->lines->firstWhere('item_name', 'Lab Chair');
        $deskLine = $demand->lines->firstWhere('item_name', 'Lab Desk');

        $vendorA = Vendor::create(['name' => 'Vendor Alpha Furnishings']);
        $vendorB = Vendor::create(['name' => 'Vendor Beta Woods']);

        $orderService = app(OrderService::class);

        // 3. Create PO #1 for 60 chairs to Vendor A
        $order1 = $orderService->create([
            'demand_id' => $demand->id,
            'vendor_id' => $vendorA->id,
            'lines' => [
                ['demand_line_id' => $chairLine->id, 'quantity_ordered' => 60, 'unit_price' => 1000.00],
            ],
        ], $buyer);

        $this->assertDatabaseHas('purchase_orders', ['id' => $order1->id, 'vendor_id' => $vendorA->id]);
        $this->assertDatabaseHas('purchase_order_lines', [
            'purchase_order_id' => $order1->id,
            'demand_line_id' => $chairLine->id,
            'quantity_ordered' => 60,
            'line_total' => '60000.00',
        ]);

        // Demand should still have 40 chairs and 50 desks remaining to order
        $chairLine->refresh();
        $this->assertEquals(60, $chairLine->orderedQty());
        $this->assertEquals(40, $chairLine->remainingToOrderQty());
        $this->assertFalse($demand->fresh()->isFullyOrdered());

        // 4. Create PO #2 for remaining 40 chairs and 50 desks to Vendor B
        $order2 = $orderService->create([
            'demand_id' => $demand->id,
            'vendor_id' => $vendorB->id,
            'lines' => [
                ['demand_line_id' => $chairLine->id, 'quantity_ordered' => 40, 'unit_price' => 1000.00],
                ['demand_line_id' => $deskLine->id, 'quantity_ordered' => 50, 'unit_price' => 3000.00],
            ],
        ], $buyer);

        $this->assertDatabaseHas('purchase_orders', ['id' => $order2->id, 'vendor_id' => $vendorB->id]);
        $this->assertDatabaseHas('purchase_order_lines', [
            'purchase_order_id' => $order2->id,
            'demand_line_id' => $chairLine->id,
            'quantity_ordered' => 40,
        ]);
        $this->assertDatabaseHas('purchase_order_lines', [
            'purchase_order_id' => $order2->id,
            'demand_line_id' => $deskLine->id,
            'quantity_ordered' => 50,
        ]);

        // Now demand is fully ordered
        $this->assertTrue($demand->fresh()->isFullyOrdered());
        $this->assertFalse($orderService->awaitingOrder()->contains('id', $demand->id));

        // 5. Attempting to place a 3rd order for chairs against this demand must fail
        $this->expectException(ValidationException::class);
        $orderService->create([
            'demand_id' => $demand->id,
            'vendor_id' => $vendorA->id,
            'lines' => [
                ['demand_line_id' => $chairLine->id, 'quantity_ordered' => 10, 'unit_price' => 1000.00],
            ],
        ], $buyer);
    }
}
