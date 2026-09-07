<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\DemandStatus;
use App\Enums\MatchStatus;
use App\Enums\ReceiptCondition;
use App\Models\DemandForm;
use App\Models\Location;
use App\Models\Vendor;
use App\Services\BillService;
use App\Services\DemandService;
use App\Services\OrderService;
use Tests\TestCase;

class LineLevelThreeWayMatchTest extends TestCase
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

    public function test_line_level_matching_detects_rate_and_quantity_variances(): void
    {
        $initiator = $this->staff('p.karki@prativa.edu.np');
        $buyer = $this->staff('purchase@prativa.edu.np');
        $receiver = $this->staff('store@prativa.edu.np');
        $accountant = $this->staff('accounts@prativa.edu.np');
        $location = Location::first();

        // 1. Create and approve demand
        $demand = app(DemandService::class)->create(
            lines: [
                ['item_name' => 'CAT6 Cable Reel', 'quantity' => 10, 'unit_rate' => 5000.00],
            ],
            department: 'IT Dept',
            justification: 'Server room accessories',
            user: $initiator,
            needByDate: now()->addDays(7)->toDateString(),
        );

        $this->approveFully($demand);
        $cableLine = $demand->fresh()->lines->first();

        $vendor = Vendor::create(['name' => 'Tech Supply Nepal']);

        // 2. Place PO for 10 units @ 5000
        $order = app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_id' => $vendor->id,
            'lines' => [
                ['demand_line_id' => $cableLine->id, 'quantity_ordered' => 10, 'unit_price' => 5000.00],
            ],
        ], $buyer);

        $poLine = $order->fresh()->lines->first();

        // 3. Receive 6 units
        app(OrderService::class)->receive(
            orderId: $order->id,
            lines: [
                ['purchase_order_line_id' => $poLine->id, 'qty_received' => 6],
            ],
            meta: [
                'location_id' => $location->id,
                'condition' => ReceiptCondition::GOOD,
            ],
            user: $receiver
        );

        $billService = app(BillService::class);

        // Case A: Bill for 6 units @ 5000 (Exactly matches delivered quantity and rate) -> MATCHED
        $billMatched = $billService->create([
            'bill_no' => 'INV-MATCH-001',
            'bill_date' => now()->toDateString(),
            'purchase_order_id' => $order->id,
            'bill_amount' => 30000.00,
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'quantity' => 6,
                    'unit_price' => 5000.00,
                    'description' => 'CAT6 Cable Reel',
                ],
            ],
        ], $accountant);

        $this->assertEquals(MatchStatus::MATCHED, $billMatched->match_status);
        $this->assertDatabaseHas('bill_lines', [
            'bill_id' => $billMatched->id,
            'purchase_order_line_id' => $poLine->id,
            'quantity' => 6,
            'unit_price' => '5000.00',
            'line_total' => '30000.00',
        ]);

        // Case B: Bill with rate variance (Invoiced @ 5500 instead of agreed 5000) -> MISMATCH
        $billPriceMismatch = $billService->create([
            'bill_no' => 'INV-RATE-ERR-002',
            'bill_date' => now()->toDateString(),
            'purchase_order_id' => $order->id,
            'bill_amount' => 33000.00,
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'quantity' => 6,
                    'unit_price' => 5500.00,
                    'description' => 'CAT6 Cable Reel',
                ],
            ],
        ], $accountant);

        $this->assertEquals(MatchStatus::MISMATCH, $billPriceMismatch->match_status);

        // Case C: Bill with quantity variance (Invoiced for 8 units when only 6 were verified received) -> MISMATCH
        $billQtyMismatch = $billService->create([
            'bill_no' => 'INV-QTY-ERR-003',
            'bill_date' => now()->toDateString(),
            'purchase_order_id' => $order->id,
            'bill_amount' => 40000.00,
            'lines' => [
                [
                    'purchase_order_line_id' => $poLine->id,
                    'quantity' => 8,
                    'unit_price' => 5000.00,
                    'description' => 'CAT6 Cable Reel',
                ],
            ],
        ], $accountant);

        $this->assertEquals(MatchStatus::MISMATCH, $billQtyMismatch->match_status);
    }
}
