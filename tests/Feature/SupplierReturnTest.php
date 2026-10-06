<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\DemandStatus;
use App\Enums\ReceiptCondition;
use App\Models\DemandForm;
use App\Models\Location;
use App\Models\Vendor;
use App\Services\DemandService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\SupplierReturnService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierReturnTest extends TestCase
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

    public function test_supplier_return_deducts_inventory_and_records_accounting_entry(): void
    {
        $initiator = $this->staff('p.karki@prativa.edu.np');
        $buyer = $this->staff('purchase@prativa.edu.np');
        $receiver = $this->staff('store@prativa.edu.np');
        $location = Location::first();

        // 1. Demand, approval, PO
        $demand = app(DemandService::class)->create(
            lines: [
                ['item_name' => 'LED Desk Lamp', 'quantity' => 20, 'unit_rate' => 1500.00],
            ],
            department: 'Classroom',
            justification: 'Desk lamps',
            user: $initiator,
            needByDate: now()->addDays(5)->toDateString(),
        );

        $this->approveFully($demand);
        $lampLine = $demand->fresh()->lines->first();

        $vendor = Vendor::create(['name' => 'Lumi Lighting Pvt Ltd']);

        $order = app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_id' => $vendor->id,
            'lines' => [
                ['demand_line_id' => $lampLine->id, 'quantity_ordered' => 20, 'unit_price' => 1500.00],
            ],
        ], $buyer);

        $poLine = $order->fresh()->lines->first();

        // 2. Receive all 20 units
        $receiptResult = app(OrderService::class)->receive(
            orderId: $order->id,
            lines: [
                ['purchase_order_line_id' => $poLine->id, 'qty_received' => 20],
            ],
            meta: [
                'location_id' => $location->id,
                'condition' => ReceiptCondition::GOOD,
            ],
            user: $receiver
        );

        $receipt = $receiptResult['receipt']->fresh();
        $grLine = $receipt->lines->first();
        $itemTypeId = $grLine->demandLine->item_type_id;

        $inventoryService = app(InventoryService::class);
        $stockBefore = $inventoryService->currentQuantity($itemTypeId, $location->id);
        $this->assertEquals(20, $stockBefore);

        // 3. Return 5 defective units back to supplier
        $returnService = app(SupplierReturnService::class);
        $supplierReturn = $returnService->create([
            'goods_receipt_id' => $receipt->id,
            'reason' => '5 units had damaged power adapters',
            'lines' => [
                [
                    'goods_receipt_line_id' => $grLine->id,
                    'quantity' => 5,
                    'reason' => 'Damaged power adapters',
                ],
            ],
        ], $receiver);

        $this->assertDatabaseHas('supplier_returns', [
            'id' => $supplierReturn->id,
            'vendor_id' => $vendor->id,
            'total_amount' => '7500.00',
            'status' => 'POSTED',
        ]);

        $this->assertDatabaseHas('supplier_return_lines', [
            'supplier_return_id' => $supplierReturn->id,
            'goods_receipt_line_id' => $grLine->id,
            'quantity' => 5,
            'unit_price' => '1500.00',
            'line_total' => '7500.00',
        ]);

        // Stock in ledger should be reduced from 20 to 15
        $stockAfter = $inventoryService->currentQuantity($itemTypeId, $location->id);
        $this->assertEquals(15, $stockAfter);

        // Double-entry accounting entry created: Dr 2100 GRNI, Cr 1200 Inventory
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => 'SUPPLIER_RETURN',
            'reference_id' => $supplierReturn->id,
        ]);

        // 4. Cannot return more units than were received
        $this->expectException(ValidationException::class);
        $returnService->create([
            'goods_receipt_id' => $receipt->id,
            'reason' => 'Over return attempt',
            'lines' => [
                [
                    'goods_receipt_line_id' => $grLine->id,
                    'quantity' => 25,
                ],
            ],
        ], $receiver);
    }
}
