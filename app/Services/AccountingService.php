<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Bill;
use App\Models\GoodsReceipt;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PettyCashToken;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Support\Money;
use App\Support\RefCounter;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    public function __construct(
        private AuditLogger $audit,
        private TenantContext $tenant,
    ) {}

    private function tenantId(): string
    {
        return $this->tenant->idOrFail();
    }

    /**
     * Posts a balanced double-entry journal entry.
     * Enforces that total debits strictly equal total credits.
     *
     * @param  array<int, array{account_code: string, debit?: string|float, credit?: string|float, description?: string|null}>  $lines
     */
    public function postJournalEntry(
        string $entryDate,
        string $memo,
        array $lines,
        User $user,
        ?string $refType = null,
        ?string $refId = null,
    ): JournalEntry {
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => 'Journal entry requires at least two lines.']);
        }

        $codes = collect($lines)->pluck('account_code')->unique()->all();
        $accounts = Account::whereIn('code', $codes)->get()->keyBy('code');

        $missing = array_diff($codes, $accounts->keys()->all());
        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'lines' => 'Unknown account code(s): '.implode(', ', $missing),
            ]);
        }

        $prepared = collect($lines)->map(function ($l) use ($accounts) {
            $account = $accounts->get($l['account_code']);
            $debit = Money::of($l['debit'] ?? 0);
            $credit = Money::of($l['credit'] ?? 0);

            return [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $l['description'] ?? null,
            ];
        });

        $totalDebit = Money::sum($prepared->pluck('debit'));
        $totalCredit = Money::sum($prepared->pluck('credit'));

        if (! Money::eq($totalDebit, $totalCredit)) {
            throw ValidationException::withMessages([
                'lines' => sprintf(
                    'Journal entry is unbalanced. Total Debits: %s, Total Credits: %s (Difference: %s). Total Debit must equal Total Credit.',
                    Money::npr($totalDebit),
                    Money::npr($totalCredit),
                    Money::npr(Money::abs(Money::sub($totalDebit, $totalCredit))),
                ),
            ]);
        }

        if (Money::isZero($totalDebit)) {
            throw ValidationException::withMessages([
                'lines' => 'Journal entry amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($entryDate, $memo, $prepared, $user, $refType, $refId, $totalDebit) {
            ['ref' => $entryNo, 'fiscal_year' => $fiscalYear] = RefCounter::next('JV');

            $entry = JournalEntry::create([
                'entry_no' => $entryNo,
                'fiscal_year' => $fiscalYear,
                'entry_date' => $entryDate,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'memo' => $memo,
                'posted_by_id' => $user->id,
            ]);

            foreach ($prepared as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'],
                ]);
            }

            $this->audit->record(
                action: 'JOURNAL_POSTED',
                entity: 'journal_entries',
                entityId: $entry->id,
                detail: sprintf(
                    '%s posted by %s on %s for %s (%s lines): %s',
                    $entryNo,
                    $user->full_name,
                    $entryDate,
                    Money::npr($totalDebit),
                    $prepared->count(),
                    $memo,
                ),
                actor: $user,
                after: [
                    'entry_no' => $entryNo,
                    'amount' => $totalDebit,
                    'reference_type' => $refType,
                    'reference_id' => $refId,
                ],
            );

            return $entry->load(['lines.account', 'postedBy']);
        });
    }

    /**
     * Automated double-entry accrual when goods are received:
     * Dr 1200 Inventory Asset
     * Cr 2100 Goods Received Not Invoiced (GRNI)
     */
    public function recordGoodsReceiptAccrual(GoodsReceipt $receipt, ?string $totalValue = null, ?User $user = null): ?JournalEntry
    {
        $user ??= $receipt->receivedBy ?? auth()->user();

        if (! $user) {
            $user = User::where('id', $receipt->received_by_id)->first();
        }

        if ($totalValue === null) {
            $receipt->loadMissing(['lines.demandLine', 'purchaseOrder.vendor']);
            $total = '0.00';
            foreach ($receipt->lines as $rLine) {
                $rate = $rLine->demandLine?->unit_rate ?? 0;
                $total = Money::add($total, Money::mul($rate, $rLine->qty_received));
            }
            $totalValue = $total;
        }

        if (Money::isZero($totalValue)) {
            return null;
        }

        $receipt->loadMissing('purchaseOrder.vendor');

        return $this->postJournalEntry(
            entryDate: $receipt->received_at ? Carbon::parse($receipt->received_at)->toDateString() : now()->toDateString(),
            memo: "Goods receipt accrual for PO {$receipt->purchaseOrder->ref} ({$receipt->purchaseOrder->vendor->name})",
            lines: [
                ['account_code' => '1200', 'debit' => $totalValue, 'credit' => 0, 'description' => 'Stock inventory receipt'],
                ['account_code' => '2100', 'debit' => 0, 'credit' => $totalValue, 'description' => 'Unbilled receipt liability (GRNI)'],
            ],
            user: $user,
            refType: 'GOODS_RECEIPT',
            refId: $receipt->id,
        );
    }

    /**
     * Automated double-entry invoice liability when a supplier bill is entered:
     * For PO bills:
     *   Dr 2100 GRNI (Net Amount)
     *   Dr 1300 VAT Receivable (VAT Amount)
     *   Cr 2000 Accounts Payable (Total Bill Amount)
     * For Direct bills:
     *   Dr 5010 Educational & Office Supplies (Net Amount)
     *   Dr 1300 VAT Receivable (VAT Amount)
     *   Cr 2000 Accounts Payable (Total Bill Amount)
     */
    public function recordBill(Bill $bill, ?User $user = null): JournalEntry
    {
        $user ??= $bill->enteredBy ?? auth()->user();

        if (! $user) {
            $user = User::where('id', $bill->entered_by_id)->first();
        }

        $bill->loadMissing('vendor');

        $net = Money::sub($bill->bill_amount, $bill->vat_amount);
        $lines = [];

        if ($bill->purchase_order_id) {
            $lines[] = ['account_code' => '2100', 'debit' => $net, 'credit' => 0, 'description' => "GRNI clear for Bill {$bill->bill_no}"];
        } else {
            $lines[] = ['account_code' => '5010', 'debit' => $net, 'credit' => 0, 'description' => "Direct purchase for Bill {$bill->bill_no}"];
        }

        if (Money::gt($bill->vat_amount, 0)) {
            $lines[] = ['account_code' => '1300', 'debit' => $bill->vat_amount, 'credit' => 0, 'description' => "Input VAT on Bill {$bill->bill_no}"];
        }

        $lines[] = ['account_code' => '2000', 'debit' => 0, 'credit' => $bill->bill_amount, 'description' => "AP liability for {$bill->vendor->name} (Bill {$bill->bill_no})"];

        return $this->postJournalEntry(
            entryDate: $bill->bill_date->toDateString(),
            memo: "Supplier invoice {$bill->bill_no} from {$bill->vendor->name}",
            lines: $lines,
            user: $user,
            refType: 'BILL',
            refId: $bill->id,
        );
    }

    /**
     * Automated double-entry payment disbursement:
     * Dr 2000 Accounts Payable
     * Cr 1020 Operating Bank Account (or 1010 Cash)
     */
    public function recordPayment(Payment $payment, ?User $user = null, string $creditAccountCode = '1020'): JournalEntry
    {
        $user ??= $payment->paidBy ?? auth()->user();

        if (! $user) {
            $user = User::where('id', $payment->paid_by_id)->first();
        }

        $payment->loadMissing('vendor');

        $methodValue = is_object($payment->payment_method) ? $payment->payment_method->value : $payment->payment_method;

        return $this->postJournalEntry(
            entryDate: $payment->payment_date->toDateString(),
            memo: "Payment voucher {$payment->voucher_no} to {$payment->vendor->name}",
            lines: [
                ['account_code' => '2000', 'debit' => $payment->amount, 'credit' => 0, 'description' => "Settlement to {$payment->vendor->name}"],
                ['account_code' => $creditAccountCode, 'debit' => 0, 'credit' => $payment->amount, 'description' => "Disbursement via {$methodValue}"],
            ],
            user: $user,
            refType: 'PAYMENT',
            refId: $payment->id,
        );
    }

    /**
     * Automated double-entry posting when goods are returned to a supplier:
     * Dr 2100 GRNI / Vendor liability reduction
     * Cr 1200 Inventory Asset reduction
     */
    public function recordSupplierReturn(SupplierReturn $return, ?User $user = null): JournalEntry
    {
        $user ??= $return->returnedBy ?? auth()->user();

        if (! $user) {
            $user = User::where('id', $return->returned_by_id)->first();
        }

        $return->loadMissing('vendor');

        return $this->postJournalEntry(
            entryDate: $return->returned_at ? Carbon::parse($return->returned_at)->toDateString() : now()->toDateString(),
            memo: "Supplier return {$return->ref} to {$return->vendor->name}",
            lines: [
                ['account_code' => '2100', 'debit' => $return->total_amount, 'credit' => 0, 'description' => "GRNI adjustment for return {$return->ref}"],
                ['account_code' => '1200', 'debit' => 0, 'credit' => $return->total_amount, 'description' => "Inventory reduction for return {$return->ref}"],
            ],
            user: $user,
            refType: 'SUPPLIER_RETURN',
            refId: $return->id,
        );
    }

    /**
     * Automated double-entry petty cash settlement:
     * Dr 5030 Utilities & General Operations Expense
     * Cr 1030 Petty Cash Float
     */
    public function recordPettyCashDisbursement(PettyCashToken $token, User $user): JournalEntry
    {
        return $this->postJournalEntry(
            entryDate: $token->paid_at ? Carbon::parse($token->paid_at)->toDateString() : now()->toDateString(),
            memo: "Petty cash payout on {$token->serial} ({$token->vendor_name}) to {$token->claimant_name}",
            lines: [
                ['account_code' => '5030', 'debit' => $token->amount, 'credit' => 0, 'description' => $token->purpose],
                ['account_code' => '1030', 'debit' => 0, 'credit' => $token->amount, 'description' => 'Petty cash float disbursement'],
            ],
            user: $user,
            refType: 'PETTY_CASH',
            refId: $token->id,
        );
    }

    /**
     * Generates a Trial Balance as of a given date.
     *
     * @return array{accounts: array, total_debit: string, total_credit: string, variance: string, is_balanced: bool}
     */
    public function trialBalance(?string $asOfDate = null): array
    {
        $tenantId = $this->tenantId();
        $date = $asOfDate ?: now()->toDateString();

        $rows = DB::select('
            SELECT a.id,
                   a.code,
                   a.name,
                   a.type,
                   COALESCE(bal.total_debit, 0) AS total_debit,
                   COALESCE(bal.total_credit, 0) AS total_credit
            FROM accounts a
            LEFT JOIN (
                SELECT jel.account_id,
                       SUM(jel.debit) AS total_debit,
                       SUM(jel.credit) AS total_credit
                FROM journal_entry_lines jel
                JOIN journal_entries je ON je.id = jel.journal_entry_id
                WHERE je.tenant_id = ? AND je.entry_date <= ?
                GROUP BY jel.account_id
            ) bal ON bal.account_id = a.id
            WHERE a.tenant_id = ? AND a.is_active = 1
            ORDER BY a.code ASC
        ', [$tenantId, $date, $tenantId]);

        $accounts = [];
        $grandDebit = '0.00';
        $grandCredit = '0.00';

        foreach ($rows as $r) {
            $debit = Money::of($r->total_debit);
            $credit = Money::of($r->total_credit);
            $grandDebit = Money::add($grandDebit, $debit);
            $grandCredit = Money::add($grandCredit, $credit);

            $accounts[] = [
                'account' => (object) [
                    'id' => $r->id,
                    'code' => $r->code,
                    'name' => $r->name,
                    'type' => AccountType::tryFrom($r->type) ?? $r->type,
                ],
                'code' => $r->code,
                'name' => $r->name,
                'type' => $r->type,
                'debit' => $debit,
                'credit' => $credit,
                'total_debit' => $debit,
                'total_credit' => $credit,
                'net_balance' => Money::sub($debit, $credit),
            ];
        }

        $variance = Money::sub($grandDebit, $grandCredit);

        return [
            'accounts' => $accounts,
            'total_debit' => $grandDebit,
            'total_credit' => $grandCredit,
            'variance' => $variance,
            'is_balanced' => Money::isZero($variance),
        ];
    }

    /**
     * General Ledger for a specific account.
     */
    public function generalLedger(string $accountCode, ?string $from = null, ?string $to = null): array
    {
        $account = Account::where('code', $accountCode)->firstOrFail();

        $lines = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('users as u', 'u.id', '=', 'je.posted_by_id')
            ->where('jel.tenant_id', $this->tenantId())
            ->where('jel.account_id', $account->id)
            ->when($from, fn ($q) => $q->where('je.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('je.entry_date', '<=', $to))
            ->select([
                'je.entry_no', 'je.entry_date', 'je.memo', 'je.reference_type',
                'jel.debit', 'jel.credit', 'jel.description', 'u.full_name as posted_by',
            ])
            ->orderBy('je.entry_date')
            ->orderBy('je.entry_no')
            ->get();

        return [
            'account' => $account,
            'lines' => $lines,
            'total_debit' => Money::of($lines->sum('debit')),
            'total_credit' => Money::of($lines->sum('credit')),
        ];
    }
}
