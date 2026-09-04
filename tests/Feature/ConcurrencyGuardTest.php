<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\ReceiptCondition;
use App\Models\DemandForm;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Services\BillService;
use App\Services\DemandService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\PettyCashService;
use App\Services\ReportService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Rules the services enforced by reading first and writing second. A read that
 * sits outside the write's transaction is a rule two simultaneous requests can
 * both walk past, so each one here is checked twice: once for the readable
 * refusal, and once against the database that has to hold it regardless.
 */
class ConcurrencyGuardTest extends TestCase
{
    private function approvedDemand(): DemandForm
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

        return $demand->fresh();
    }

    private function blockA(): string
    {
        return Location::where('code', 'A')->firstOrFail()->id;
    }

    public function test_one_approved_demand_cannot_carry_two_purchase_orders(): void
    {
        $demand = $this->approvedDemand();
        $orders = app(OrderService::class);
        $buyer = $this->staff('purchase@prativa.edu.np');

        $first = $orders->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 60000,
        ], $buyer);

        try {
            $orders->create([
                'demand_id' => $demand->id,
                'vendor_name' => 'Some Other Supplier',
                'order_amount' => 60000,
            ], $buyer);

            $this->fail('A second order was accepted against one approval.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($first->ref, $e->getMessage());
        }

        $this->assertSame(1, PurchaseOrder::where('demand_id', $demand->id)->count());
    }

    public function test_the_database_refuses_a_second_order_even_when_the_service_is_bypassed(): void
    {
        $demand = $this->approvedDemand();

        $first = app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 60000,
        ], $this->staff('purchase@prativa.edu.np'));

        $this->expectException(QueryException::class);

        PurchaseOrder::create([
            'ref' => 'PO-9999-0001',
            'fiscal_year' => $first->fiscal_year,
            'demand_id' => $demand->id,
            'vendor_id' => $first->vendor_id,
            'order_amount' => 60000,
            'ordered_by_id' => $first->ordered_by_id,
        ]);
    }

    public function test_a_receipt_for_more_than_was_ordered_is_refused_in_words(): void
    {
        $demand = $this->approvedDemand();

        $order = app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 60000,
        ], $this->staff('purchase@prativa.edu.np'));

        try {
            app(OrderService::class)->receive($order->id, [
                ['demand_line_id' => $demand->lines->first()->id, 'qty_received' => 400],
            ], [
                'location_id' => $this->blockA(),
                'condition' => ReceiptCondition::GOOD,
            ], $this->staff('store@prativa.edu.np'));

            $this->fail('A receipt recorded ten times what was ordered.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('40 ordered', $e->getMessage());
        }
    }

    public function test_a_token_cannot_be_settled_twice(): void
    {
        $petty = app(PettyCashService::class);

        $token = $petty->issue([
            'bill_no' => 'SS/2082/44',
            'vendor_name' => 'Sagarmatha Stationers',
            'amount' => 1200,
            'claimant_name' => 'R. Thapa',
            'purpose' => 'Marker pens for the staff room',
            'bill_sighted' => true,
        ], $this->staff('accounts@prativa.edu.np'));

        $petty->markPaid($token->id, $this->staff('accounts2@prativa.edu.np'));

        $this->expectException(ValidationException::class);

        $petty->markPaid($token->id, $this->staff('accounts2@prativa.edu.np'));
    }

    public function test_spend_by_category_is_not_inflated_by_a_second_bill(): void
    {
        $demand = $this->approvedDemand();
        $orders = app(OrderService::class);
        $bills = app(BillService::class);
        $accounts = $this->staff('accounts@prativa.edu.np');

        $order = $orders->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 60000,
        ], $this->staff('purchase@prativa.edu.np'));

        $orders->receive($order->id, [
            ['demand_line_id' => $demand->lines->first()->id, 'qty_received' => 40],
        ], [
            'location_id' => $this->blockA(),
            'condition' => ReceiptCondition::GOOD,
        ], $this->staff('store@prativa.edu.np'));

        // Two part bills against one order — the shape that used to double both
        // the approved value and the units received in the management report.
        $bills->create([
            'bill_no' => 'HFU/1', 'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(), 'bill_amount' => 30000,
        ], $accounts);

        $bills->create([
            'bill_no' => 'HFU/2', 'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(), 'bill_amount' => 30000,
        ], $accounts);

        $report = app(ReportService::class)->spendByCategory();

        $this->assertSame(60000.0, $report->sum(fn ($r) => (float) $r->approved_value));
        $this->assertSame(40.0, $report->sum(fn ($r) => (float) $r->units_received));
        $this->assertSame(2, $report->sum(fn ($r) => (int) $r->bills));
    }

    public function test_a_count_sheet_does_not_ask_the_database_once_per_line(): void
    {
        $block = $this->blockA();

        $lines = ItemType::active()->limit(30)->get()
            ->map(fn (ItemType $item) => [
                'item_type_id' => $item->id,
                'location_id' => $block,
                'quantity' => 7,
            ])->all();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        app(InventoryService::class)->submitCount($lines, $this->staff('auditor@prativa.edu.np'));

        // Thirty ledger rows and thirty audit rows have to be written. What must
        // not happen on top of them is a separate read of the standing figure
        // for every line on the sheet.
        $this->assertLessThan(75, $queries, "A thirty-line count sheet cost {$queries} queries.");
    }
}
