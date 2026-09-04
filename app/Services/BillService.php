<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\TokenStatus;
use App\Models\Bill;
use App\Models\PettyCashToken;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Support\FiscalYear;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillService
{
    public function __construct(
        private AuditLogger $audit,
        private TenantContext $tenant,
        private Notifier $notify,
    ) {}

    /** Received orders with no bill against them yet. */
    public function awaitingBill(): Collection
    {
        return PurchaseOrder::query()
            ->whereHas('receipt')
            ->whereDoesntHave('bills')
            ->with(['vendor', 'demand', 'receipt.receivedBy'])
            ->orderBy('ordered_at')
            ->get();
    }

    /**
     * @param  array{bill_no: string, purchase_order_id?: string|null, vendor_id?: string|null, vendor_name?: string|null, bill_date: string, bill_amount: string|float, vat_amount?: string|float|null, attachment_path?: string|null}  $data
     */
    public function create(array $data, User $user): Bill
    {
        $billNo = trim($data['bill_no']);

        $duplicate = Bill::where('bill_no', $billNo)->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'bill_no' => sprintf(
                    'Bill number %s is already on record, entered %s. The same bill cannot be claimed twice.',
                    $billNo,
                    $duplicate->entered_at->toDateString(),
                ),
            ]);
        }

        // The other half of the double claim. Petty cash already refuses a bill
        // that sits in this register; until this check was added the reverse was
        // open, so the same bill could be paid twice — once over the counter and
        // once against a purchase order. A trigger refuses it outright now; this
        // is here to say so in words first.
        $tokenised = PettyCashToken::where('bill_no', $billNo)
            ->where('status', '!=', TokenStatus::VOIDED)
            ->first();

        if ($tokenised) {
            throw ValidationException::withMessages([
                'bill_no' => sprintf(
                    'Bill %s was already claimed from petty cash on token %s (%s). It cannot also be entered here.',
                    $billNo,
                    $tokenised->serial,
                    $tokenised->issued_at->toDateString(),
                ),
            ]);
        }

        $approvedAmount = null;
        $orderedAmount = null;
        $vendorId = $data['vendor_id'] ?? null;

        if (! empty($data['purchase_order_id'])) {
            $order = PurchaseOrder::with(['demand', 'receipt'])->findOrFail($data['purchase_order_id']);

            if (! $order->receipt) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'The goods have not been verified as received yet. A bill cannot be entered before receipt.',
                ]);
            }

            $approvedAmount = Money::of($order->demand->total_amount);
            $orderedAmount = Money::of($order->order_amount);
            $vendorId = $order->vendor_id;
        }

        if (! $vendorId) {
            if (empty($data['vendor_name'])) {
                throw ValidationException::withMessages(['vendor_name' => 'Name the vendor.']);
            }

            $vendorId = Vendor::firstOrCreate(['name' => trim($data['vendor_name'])])->id;
        }

        // The three-way test. A bill matches only if it equals what was ordered
        // AND does not exceed what was approved.
        $billed = Money::of($data['bill_amount']);
        $variance = $orderedAmount === null ? '0.00' : Money::sub($billed, $orderedAmount);
        $matched = $orderedAmount !== null
            && Money::isZero($variance)
            && ($approvedAmount === null || Money::lte($billed, $approvedAmount));

        $bill = Bill::create([
            'bill_no' => $billNo,
            'fiscal_year' => FiscalYear::label(Carbon::parse($data['bill_date'])),
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'vendor_id' => $vendorId,
            'bill_date' => $data['bill_date'],
            'bill_amount' => $billed,
            'vat_amount' => Money::of($data['vat_amount'] ?? 0),
            'approved_amount' => $approvedAmount,
            'ordered_amount' => $orderedAmount,
            'variance_amount' => $variance,
            'match_status' => $matched ? MatchStatus::MATCHED : MatchStatus::MISMATCH,
            'attachment_path' => $data['attachment_path'] ?? null,
            'entered_by_id' => $user->id,
        ]);

        $bill->load('vendor');

        $this->audit->record(
            action: $matched ? 'BILL_ENTERED_MATCHED' : 'BILL_ENTERED_MISMATCH',
            entity: 'bills',
            entityId: $bill->id,
            detail: sprintf(
                'Bill %s from %s for %s%s%s',
                $billNo,
                $bill->vendor->name,
                Money::npr($billed),
                $orderedAmount !== null
                    ? '; ordered '.Money::npr($orderedAmount).', approved '.Money::npr($approvedAmount)
                    : ' (no purchase order attached)',
                $matched ? ' — matched' : ' — MISMATCH of '.Money::npr(Money::abs($variance)),
            ),
            actor: $user,
            after: [
                'billed' => $billed,
                'ordered' => $orderedAmount,
                'approved' => $approvedAmount,
                'variance' => $variance,
            ],
        );

        // Only when it did not match. A bill that agrees with its order needs
        // nobody's attention, and telling people about those is how they learn
        // to ignore the ones that matter.
        if ($bill->match_status !== MatchStatus::MATCHED) {
            $this->notify->billFlagged($bill, $user);
        }

        return $bill;
    }

    public function list(?MatchStatus $status = null, int $perPage = 25): LengthAwarePaginator
    {
        return Bill::query()
            ->when($status, fn ($q) => $q->where('match_status', $status))
            ->with([
                'vendor:id,name',
                'enteredBy:id,full_name',
                'clearedBy:id,full_name',
                'purchaseOrder:id,ref',
            ])
            ->orderByDesc('entered_at')
            ->paginate($perPage);
    }

    /** The live three-way view, straight from the database. */
    public function threeWay(): Collection
    {
        // Raw, so the global scope does not apply — the predicate is the only
        // thing keeping one school out of another school's bill register.
        return collect(DB::select(
            'SELECT * FROM v_three_way_match WHERE tenant_id = ? ORDER BY bill_date DESC',
            [$this->tenant->idOrFail()],
        ));
    }

    /**
     * Accounts accepts a difference. Nothing is erased: the original three
     * figures stay on record, the status becomes VARIANCE_CLEARED, and the
     * written reason is attached permanently with the name of whoever accepted
     * it. A CHECK constraint refuses a cleared variance without one.
     */
    public function clearVariance(string $billId, string $note, User $user): Bill
    {
        $bill = Bill::with('vendor')->findOrFail($billId);

        if ($bill->match_status !== MatchStatus::MISMATCH) {
            throw ValidationException::withMessages([
                'bill' => 'This bill is not flagged, so there is nothing to clear.',
            ]);
        }

        if (mb_strlen(trim($note)) < 10) {
            throw ValidationException::withMessages([
                'variance_note' => 'Write at least a sentence explaining why the difference is accepted.',
            ]);
        }

        $before = $bill->match_status;

        $bill->update([
            'match_status' => MatchStatus::VARIANCE_CLEARED,
            'variance_note' => $note,
            'cleared_by_id' => $user->id,
            'cleared_at' => now(),
        ]);

        $this->audit->record(
            action: 'BILL_VARIANCE_CLEARED',
            entity: 'bills',
            entityId: $bill->id,
            detail: sprintf(
                '%s accepted a variance of %s on bill %s (%s): %s',
                $user->full_name,
                Money::npr(Money::abs($bill->variance_amount)),
                $bill->bill_no,
                $bill->vendor->name,
                $note,
            ),
            actor: $user,
            before: ['match_status' => $before->value],
            after: ['match_status' => MatchStatus::VARIANCE_CLEARED->value, 'variance_note' => $note],
        );

        return $bill->fresh();
    }
}
