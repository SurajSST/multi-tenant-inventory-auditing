<?php

namespace Tests\Feature;

use App\Enums\ApprovalAction;
use App\Enums\ReceiptCondition;
use App\Enums\TokenStatus;
use App\Models\ItemType;
use App\Models\Location;
use App\Services\BillService;
use App\Services\DemandService;
use App\Services\OrderService;
use App\Services\PettyCashService;
use App\Services\SettingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PettyCashTest extends TestCase
{
    private function token(array $overrides = [])
    {
        return app(PettyCashService::class)->issue(array_merge([
            'bill_no' => 'STN/778',
            'bill_date' => now()->toDateString(),
            'vendor_name' => 'Nepal Stationers',
            'amount' => 3000,
            'claimant_name' => 'R. Basnet',
            'purpose' => 'Exam stationery',
            'bill_sighted' => true,
        ], $overrides), $this->staff('accounts@prativa.edu.np'));
    }

    public function test_a_token_above_the_ceiling_is_refused(): void
    {
        try {
            $this->token(['amount' => 20000]);
            $this->fail('A token above the ceiling must be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('demand form', $e->validator->errors()->first());
        }
    }

    public function test_a_token_needs_the_bill_to_have_been_sighted(): void
    {
        $this->expectException(ValidationException::class);

        $this->token(['bill_sighted' => false]);
    }

    public function test_the_database_refuses_an_unsighted_or_over_ceiling_token(): void
    {
        $accounts = $this->staff('accounts@prativa.edu.np');

        $this->expectException(QueryException::class);

        DB::table('petty_cash_tokens')->insert([
            'id' => (string) Str::uuid(),
            'serial' => 'PC-9999-0001',
            'fiscal_year' => '2082/83',
            'bill_no' => 'FAKE/1',
            'vendor_name' => 'Nowhere',
            'amount' => 25000,
            'ceiling_at_issue' => 15000,
            'claimant_name' => 'Nobody',
            'purpose' => 'Bypass attempt',
            'bill_sighted' => 0,
            'issued_by_id' => $accounts->id,
        ]);
    }

    public function test_the_same_bill_cannot_be_tokenised_twice(): void
    {
        $this->token();

        $this->expectException(ValidationException::class);

        $this->token(['amount' => 500, 'purpose' => 'Second bite']);
    }

    public function test_a_bill_in_the_main_register_cannot_also_be_claimed_from_petty_cash(): void
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
            'order_amount' => 60000,
        ], $this->staff('purchase@prativa.edu.np'));

        $orders->receive($order->id, [
            ['demand_line_id' => $demand->lines->first()->id, 'qty_received' => 40],
        ], [
            'location_id' => Location::where('code', 'A')->firstOrFail()->id,
            'condition' => ReceiptCondition::GOOD,
        ], $this->staff('store@prativa.edu.np'));

        app(BillService::class)->create([
            'bill_no' => 'HFU/2082/0455',
            'purchase_order_id' => $order->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 60000,
        ], $this->staff('accounts@prativa.edu.np'));

        $this->expectException(ValidationException::class);

        $this->token(['bill_no' => 'HFU/2082/0455', 'amount' => 3000]);
    }

    public function test_the_issuer_cannot_release_their_own_token(): void
    {
        $token = $this->token();
        $petty = app(PettyCashService::class);

        try {
            $petty->markPaid($token->id, $this->staff('accounts@prativa.edu.np'));
            $this->fail('The issuer must not be able to release their own token.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('somebody else', $e->validator->errors()->first());
        }

        $paid = $petty->markPaid($token->id, $this->staff('accounts2@prativa.edu.np'));

        $this->assertSame(TokenStatus::PAID, $paid->status);
        $this->assertNotSame($paid->issued_by_id, $paid->paid_by_id);
    }

    public function test_the_ceiling_is_frozen_onto_the_token(): void
    {
        $token = $this->token();

        $this->assertSame('15000.00', (string) $token->ceiling_at_issue);

        // Lowering the ceiling later does not retrospectively invalidate it.
        app(SettingService::class)->set(
            SettingService::PETTY_CASH_CEILING, 2000,
            $this->staff('md@prativa.edu.np'),
        );

        $this->assertSame('15000.00', (string) $token->fresh()->ceiling_at_issue);
    }

    public function test_a_paid_token_cannot_be_voided(): void
    {
        $token = $this->token();
        $petty = app(PettyCashService::class);

        $petty->markPaid($token->id, $this->staff('accounts2@prativa.edu.np'));

        $this->expectException(ValidationException::class);

        $petty->void($token->id, 'Changed our mind about this claim.', $this->staff('accounts@prativa.edu.np'));
    }

    public function test_the_summary_reports_the_float_position(): void
    {
        $this->token();
        $this->token(['bill_no' => 'STN/779', 'amount' => 1200]);

        $summary = app(PettyCashService::class)->summary();

        $this->assertSame(2, $summary['tokens_issued']);
        $this->assertSame(2, $summary['awaiting_payment']);
        $this->assertSame('4200.00', $summary['awaiting_payment_value']);
    }
}
