<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\CountSource;
use App\Enums\DemandStatus;
use App\Enums\MatchStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\DemandForm;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\StockCountEntry;
use App\Services\AccountingService;
use App\Services\BillService;
use App\Services\DemandService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountsPayableAndAccountingTest extends TestCase
{
    private function approvedDemand(int $qty = 10, int $rate = 5000): DemandForm
    {
        $initiator = $this->staff('p.karki@prativa.edu.np');
        $item = ItemType::where('is_active', true)->firstOrFail();

        $demandService = app(DemandService::class);
        $demand = $demandService->create(
            lines: [
                ['item_type_id' => $item->id, 'item_name' => $item->name, 'quantity' => $qty, 'unit_rate' => $rate],
            ],
            department: 'Academic',
            justification: 'Semester supplies',
            user: $initiator,
        );

        $approvers = [
            1 => 'hod.science@prativa.edu.np',
            2 => 'admin.officer@prativa.edu.np',
            3 => 'md@prativa.edu.np',
            4 => 'chairman@prativa.edu.np',
        ];

        while ($demand->status === DemandStatus::PENDING) {
            $currentTier = $demand->current_tier;
            $approverEmail = $approvers[$currentTier];
            $demand = $demandService->decide(
                $demand->id,
                ApprovalAction::APPROVE,
                $this->staff($approverEmail),
                minuteRef: $currentTier === 4 ? 'MIN-2082-01' : null
            );
        }

        return $demand->fresh();
    }

    public function test_partial_receiving_allows_multiple_deliveries_until_complete(): void
    {
        $demand = $this->approvedDemand(qty: 10, rate: 5000);
        $orderer = $this->staff('purchase@prativa.edu.np');
        $receiver = $this->staff('store@prativa.edu.np');
        $location = Location::where('is_active', true)->firstOrFail();

        $orderService = app(OrderService::class);
        $order = $orderService->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'National Educational Supplies',
            'order_amount' => 50000,
        ], $orderer);

        $this->assertSame(OrderStatus::PLACED, $order->status);

        $line = $demand->lines->first();

        // 1. First delivery of 4 items
        $res1 = $orderService->receive($order->id, [
            ['demand_line_id' => $line->id, 'qty_received' => 4],
        ], ['location_id' => $location->id], $receiver);

        $this->assertTrue($res1['partial']);
        $this->assertSame(4, $res1['units_posted']);
        $this->assertSame(OrderStatus::PART_RECEIVED, $order->fresh()->status);

        // 2. Attempt to receive 7 items (4 + 7 = 11 > 10 ordered) must fail
        try {
            $orderService->receive($order->id, [
                ['demand_line_id' => $line->id, 'qty_received' => 7],
            ], ['location_id' => $location->id], $receiver);
            $this->fail('Over-receiving beyond remaining quantity should be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('remaining', $e->validator->errors()->first());
        }

        // 3. Second delivery of remaining 6 items
        $res2 = $orderService->receive($order->id, [
            ['demand_line_id' => $line->id, 'qty_received' => 6],
        ], ['location_id' => $location->id], $receiver);

        $this->assertFalse($res2['partial']);
        $this->assertSame(6, $res2['units_posted']);
        $this->assertSame(OrderStatus::RECEIVED, $order->fresh()->status);

        // 4. Order is now fully received, further receipt attempts must be refused
        try {
            $orderService->receive($order->id, [
                ['demand_line_id' => $line->id, 'qty_received' => 1],
            ], ['location_id' => $location->id], $receiver);
            $this->fail('Receiving against an already complete order must be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already been completely received', $e->validator->errors()->first());
        }
    }

    public function test_three_way_match_verifies_goods_actually_received(): void
    {
        $demand = $this->approvedDemand(qty: 10, rate: 5000);
        $orderer = $this->staff('purchase@prativa.edu.np');
        $receiver = $this->staff('store@prativa.edu.np');
        $accounts = $this->staff('accounts@prativa.edu.np');
        $location = Location::where('is_active', true)->firstOrFail();

        $order = app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Apex Office Tech',
            'order_amount' => 50000,
        ], $orderer);

        $line = $demand->lines->first();

        // Receive only 4 items (value = 4 * 5000 = 20,000)
        app(OrderService::class)->receive($order->id, [
            ['demand_line_id' => $line->id, 'qty_received' => 4],
        ], ['location_id' => $location->id], $receiver);

        $billService = app(BillService::class);

        // Vendor sends bill for 50,000 (all 10 items), even though only 20,000 arrived
        $mismatchedBill = $billService->create([
            'bill_no' => 'INV-APEX-001',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 50000,
        ], $accounts);

        // Must be flagged as MISMATCH because billed exceeds received goods
        $this->assertSame(MatchStatus::MISMATCH, $mismatchedBill->match_status);
        $this->assertSame('30000.00', (string) $mismatchedBill->variance_amount);

        // Vendor sends bill for 20,000 matching what actually arrived
        $matchedBill = $billService->create([
            'bill_no' => 'INV-APEX-002',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 20000,
        ], $accounts);

        $this->assertSame(MatchStatus::MATCHED, $matchedBill->match_status);
        $this->assertSame('0.00', (string) $matchedBill->variance_amount);
    }

    public function test_payment_disbursement_and_bill_settlement(): void
    {
        $demand = $this->approvedDemand(qty: 10, rate: 5000);
        $orderer = $this->staff('purchase@prativa.edu.np');
        $receiver = $this->staff('store@prativa.edu.np');
        $accounts = $this->staff('accounts@prativa.edu.np');
        $location = Location::where('is_active', true)->firstOrFail();

        $order = app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Kathmandu Paper House',
            'order_amount' => 50000,
        ], $orderer);

        $line = $demand->lines->first();
        app(OrderService::class)->receive($order->id, [
            ['demand_line_id' => $line->id, 'qty_received' => 10],
        ], ['location_id' => $location->id], $receiver);

        $bill = app(BillService::class)->create([
            'bill_no' => 'KPH-BILL-99',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 50000,
        ], $accounts);

        $this->assertSame('UNPAID', $bill->payment_status);
        $this->assertSame('0.00', (string) $bill->paid_amount);
        $this->assertSame('50000.00', (string) $bill->remainingBalance());

        $paymentService = app(PaymentService::class);

        // 1. First partial payment of 30,000
        $payment1 = $paymentService->recordPayment([
            'vendor_id' => $bill->vendor_id,
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'payment_date' => now()->toDateString(),
            'amount' => 30000,
            'reference_no' => 'NCHL-00129',
            'bank_name' => 'Nepal Bank Limited',
            'remarks' => 'Part payment for textbooks and stationery',
            'allocations' => [
                ['bill_id' => $bill->id, 'allocated_amount' => 30000],
            ],
        ], $accounts);

        $this->assertNotNull($payment1->voucher_no);
        $this->assertStringStartsWith('PV-', $payment1->voucher_no);

        $bill->refresh();
        $this->assertSame('PART_PAID', $bill->payment_status);
        $this->assertSame('30000.00', (string) $bill->paid_amount);
        $this->assertSame('20000.00', (string) $bill->remainingBalance());

        // 2. Second payment of remaining 20,000
        $payment2 = $paymentService->recordPayment([
            'vendor_id' => $bill->vendor_id,
            'payment_method' => PaymentMethod::CHEQUE,
            'payment_date' => now()->toDateString(),
            'amount' => 20000,
            'reference_no' => 'CHQ-77881',
            'allocations' => [
                ['bill_id' => $bill->id, 'allocated_amount' => 20000],
            ],
        ], $accounts);

        $bill->refresh();
        $this->assertSame('PAID', $bill->payment_status);
        $this->assertSame('50000.00', (string) $bill->paid_amount);
        $this->assertSame('0.00', (string) $bill->remainingBalance());
        $this->assertTrue($bill->isPaid());
    }

    public function test_double_entry_accounting_and_trial_balance_equilibrium(): void
    {
        $accounting = app(AccountingService::class);
        $accounts = $this->staff('accounts@prativa.edu.np');

        // Post a balanced manual journal entry
        $entry = $accounting->postJournalEntry(
            entryDate: now()->toDateString(),
            memo: 'Initial capital funding injection',
            lines: [
                ['account_code' => '1020', 'debit' => 100000, 'credit' => 0, 'description' => 'Bank deposit'],
                ['account_code' => '3000', 'debit' => 0, 'credit' => 100000, 'description' => 'School fund equity'],
            ],
            user: $accounts,
        );

        $this->assertNotNull($entry->entry_no);
        $this->assertStringStartsWith('JV-', $entry->entry_no);

        // Attempting an unbalanced entry (Debit != Credit) must be rejected
        try {
            $accounting->postJournalEntry(
                entryDate: now()->toDateString(),
                memo: 'Unbalanced entry test',
                lines: [
                    ['account_code' => '1020', 'debit' => 50000, 'credit' => 0],
                    ['account_code' => '3000', 'debit' => 0, 'credit' => 45000],
                ],
                user: $accounts,
            );
            $this->fail('Unbalanced journal entries must be rejected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('balanced', $e->validator->errors()->first());
        }

        // Check Trial Balance Equilibrium
        $tb = $accounting->trialBalance();
        $this->assertTrue($tb['is_balanced']);
        $this->assertSame('0.00', (string) $tb['variance']);
        $this->assertTrue(Money::eq($tb['total_debit'], $tb['total_credit']));
    }

    public function test_stock_transfer_and_issue_movements(): void
    {
        $inventory = app(InventoryService::class);
        $item = ItemType::where('is_active', true)->firstOrFail();
        $locations = Location::where('is_active', true)->take(2)->get();
        $locA = $locations[0];
        $locB = $locations[1];
        $auditor = $this->staff('auditor@prativa.edu.np');

        $initialA = $inventory->currentQuantity($item->id, $locA->id);
        $initialB = $inventory->currentQuantity($item->id, $locB->id);

        // Add 20 units at location A via stock count
        StockCountEntry::create([
            'item_type_id' => $item->id,
            'location_id' => $locA->id,
            'quantity' => $initialA + 20,
            'previous_qty' => $initialA,
            'source' => CountSource::OPENING_BALANCE,
            'counted_by_id' => $auditor->id,
        ]);

        $this->assertSame($initialA + 20, $inventory->currentQuantity($item->id, $locA->id));

        // Transfer 8 units from A to B
        $inventory->transfer($item->id, $locA->id, $locB->id, 8, $auditor, 'Reallocating desks');

        $this->assertSame($initialA + 12, $inventory->currentQuantity($item->id, $locA->id));
        $this->assertSame($initialB + 8, $inventory->currentQuantity($item->id, $locB->id));

        // Issue 3 units from B for laboratory use
        $inventory->issueStock($item->id, $locB->id, 3, $auditor, 'Science Department', 'Class 10 Experiment');

        $this->assertSame($initialB + 5, $inventory->currentQuantity($item->id, $locB->id));
    }
}
