<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Models\ItemType;
use App\Services\DemandService;
use App\Services\OrderService;
use App\Services\PettyCashService;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    private function createDemand()
    {
        $chair = ItemType::where('code_prefix', 'CHAIR.S')->firstOrFail();

        return app(DemandService::class)->create(
            lines: [['item_type_id' => $chair->id, 'item_name' => $chair->name, 'quantity' => 20, 'unit_rate' => 1500]],
            department: 'Computer Science',
            justification: 'Replacement computer lab chairs for student workstations.',
            user: $this->staff('p.karki@prativa.edu.np'),
        );
    }

    private function createApprovedOrder()
    {
        $demand = $this->createDemand();
        $demands = app(DemandService::class);

        while ($demand->fresh()->isPending()) {
            $approver = match ($demand->fresh()->current_tier) {
                1 => 'hod.science',
                2 => 'admin.officer',
                default => 'md',
            };
            $demands->decide($demand->id, ApprovalAction::APPROVE, $this->staff($approver.'@prativa.edu.np'), 'Approved in procurement meeting.');
        }

        return app(OrderService::class)->create([
            'demand_id' => $demand->id,
            'vendor_name' => 'Himalaya Furniture Udyog',
            'order_amount' => 30000,
        ], $this->staff('purchase@prativa.edu.np'));
    }

    private function createPettyCashToken()
    {
        return app(PettyCashService::class)->issue([
            'bill_no' => 'STN/991',
            'bill_date' => now()->toDateString(),
            'vendor_name' => 'Nepal Stationers',
            'amount' => 2500,
            'claimant_name' => 'R. Basnet',
            'purpose' => 'Urgent examination answer sheets and markers',
            'bill_sighted' => true,
        ], $this->staff('accounts@prativa.edu.np'));
    }

    public function test_bills_print_and_pdf_routes_work_for_accounts(): void
    {
        $accounts = $this->staff('accounts@prativa.edu.np');

        $printResponse = $this->actingAs($accounts)->get('/bills/print');
        $printResponse->assertOk();
        $printResponse->assertSee('Procurement Bills');
        $printResponse->assertSee('Official Procurement Audit Register');

        $pdfResponse = $this->actingAs($accounts)->get('/bills/pdf');
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThan(500, strlen($pdfResponse->getContent()));
        $this->assertSame('%PDF', substr($pdfResponse->getContent(), 0, 4));
    }

    public function test_a_teacher_cannot_print_or_download_bills_register(): void
    {
        $teacher = $this->staff('p.karki@prativa.edu.np');

        $this->actingAs($teacher)->get('/bills/print')->assertForbidden();
        $this->actingAs($teacher)->get('/bills/pdf')->assertForbidden();
    }

    public function test_demand_print_and_pdf_routes_render_clean_official_document_with_audit_trail(): void
    {
        $demand = $this->createDemand();
        $user = $this->staff('p.karki@prativa.edu.np');

        $printResponse = $this->actingAs($user)->get("/demands/{$demand->id}/print");
        $printResponse->assertOk();
        $printResponse->assertSee($demand->ref);
        $printResponse->assertSee('Copy of Audit Trail');
        $printResponse->assertSee('Requisition Raised');
        $printResponse->assertSee('Computer Science');

        $pdfResponse = $this->actingAs($user)->get("/demands/{$demand->id}/pdf");
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThan(1000, strlen($pdfResponse->getContent()));
        $this->assertSame('%PDF', substr($pdfResponse->getContent(), 0, 4));
    }

    public function test_purchase_order_print_and_pdf_routes_render_clean_official_document_with_audit_trail(): void
    {
        $order = $this->createApprovedOrder();
        $user = $this->staff('purchase@prativa.edu.np');

        $printResponse = $this->actingAs($user)->get("/orders/{$order->id}/print");
        $printResponse->assertOk();
        $printResponse->assertSee($order->ref);
        $printResponse->assertSee('Himalaya Furniture Udyog');
        $printResponse->assertSee('Purchase Order');
        $printResponse->assertSee('Audit Trail');

        $pdfResponse = $this->actingAs($user)->get("/orders/{$order->id}/pdf");
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThan(1000, strlen($pdfResponse->getContent()));
        $this->assertSame('%PDF', substr($pdfResponse->getContent(), 0, 4));
    }

    public function test_petty_cash_print_and_pdf_routes_render_clean_official_voucher_with_audit_trail(): void
    {
        $token = $this->createPettyCashToken();
        $accounts = $this->staff('accounts@prativa.edu.np');

        $printResponse = $this->actingAs($accounts)->get("/petty-cash/{$token->id}/print");
        $printResponse->assertOk();
        $printResponse->assertSee($token->serial);
        $printResponse->assertSee('R. Basnet');
        $printResponse->assertSee('Urgent examination answer sheets');
        $printResponse->assertSee('Audit Trail');

        $pdfResponse = $this->actingAs($accounts)->get("/petty-cash/{$token->id}/pdf");
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThan(1000, strlen($pdfResponse->getContent()));
        $this->assertSame('%PDF', substr($pdfResponse->getContent(), 0, 4));
    }
}
