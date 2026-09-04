<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\PettyCashToken;
use App\Models\Vendor;
use App\Services\BillService;
use App\Services\PettyCashService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * One bill, one payment.
 *
 * Petty cash already refused a bill that sat in the main register. The reverse
 * was open: a bill claimed over the counter could then be entered against a
 * purchase order and paid a second time. These tests pin both directions shut,
 * in the services and in the database underneath them.
 */
class DoubleClaimTest extends TestCase
{
    private function issueToken(string $billNo = 'SS/2082/9001'): PettyCashToken
    {
        return app(PettyCashService::class)->issue([
            'bill_no' => $billNo,
            'vendor_name' => 'Sagarmatha Stationers',
            'amount' => 2400,
            'claimant_name' => 'R. Thapa',
            'purpose' => 'Chart paper for the science exhibition',
            'bill_sighted' => true,
        ], $this->staff('accounts@prativa.edu.np'));
    }

    public function test_a_bill_paid_out_of_petty_cash_cannot_also_be_entered_in_the_register(): void
    {
        $token = $this->issueToken();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($token->serial);

        app(BillService::class)->create([
            'bill_no' => $token->bill_no,
            'vendor_name' => 'Sagarmatha Stationers',
            'bill_date' => now()->toDateString(),
            'bill_amount' => 2400,
        ], $this->staff('accounts2@prativa.edu.np'));
    }

    public function test_the_database_refuses_that_bill_even_when_the_service_is_bypassed(): void
    {
        $token = $this->issueToken();

        $this->expectException(QueryException::class);

        Bill::create([
            'bill_no' => $token->bill_no,
            'fiscal_year' => $token->fiscal_year,
            'vendor_id' => Vendor::create(['name' => 'Sagarmatha Stationers'])->id,
            'bill_date' => now()->toDateString(),
            'bill_amount' => 2400,
            'entered_by_id' => $this->staff('accounts@prativa.edu.np')->id,
        ]);
    }

    public function test_one_bill_cannot_carry_two_live_petty_cash_tokens(): void
    {
        $token = $this->issueToken();

        $this->expectException(QueryException::class);

        PettyCashToken::create([
            'serial' => 'PC-9999-0001',
            'fiscal_year' => $token->fiscal_year,
            'bill_no' => $token->bill_no,
            'vendor_name' => 'Sagarmatha Stationers',
            'amount' => 2400,
            'ceiling_at_issue' => 15000,
            'claimant_name' => 'R. Thapa',
            'purpose' => 'Claimed a second time',
            'bill_sighted' => true,
            'issued_by_id' => $this->staff('accounts@prativa.edu.np')->id,
        ]);
    }

    public function test_voiding_a_token_releases_the_bill_number_again(): void
    {
        $token = $this->issueToken();

        app(PettyCashService::class)->void(
            $token->id,
            'Claimant withdrew the claim before payment.',
            $this->staff('accounts@prativa.edu.np'),
        );

        $replacement = $this->issueToken();

        $this->assertNotSame($token->serial, $replacement->serial);
        $this->assertSame($token->bill_no, $replacement->bill_no);
    }
}
