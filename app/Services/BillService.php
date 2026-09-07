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
use Illuminate\Auth\Access\AuthorizationException;
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
        private AccountingService $accounting,
        private Notifier $notify,
    ) {}

    /** Received orders with no bill against them yet. */
    public function awaitingBill(): Collection
    {
        return PurchaseOrder::query()
            ->whereHas('receipts')
            ->whereDoesntHave('bills')
            ->with(['vendor', 'demand', 'receipts.receivedBy'])
            ->orderBy('ordered_at')
            ->get();
    }

    /**
     * @param  array{
     *     bill_no: string,
     *     purchase_order_id?: string|null,
     *     vendor_id?: string|null,
     *     vendor_name?: string|null,
     *     bill_date: string,
     *     bill_amount: string|float,
     *     vat_amount?: string|float|null,
     *     attachment_path?: string|null,
     *     lines?: array<int, array{purchase_order_line_id?: string|null, item_type_id?: string|null, description?: string|null, quantity: int, unit_price: string|float, discount?: string|float|null, tax?: string|float|null}>
     * }  $data
     */
    public function create(array $data, User $user): Bill
    {
        $billNo = trim($data['bill_no']);

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
        $receivedAmount = '0.00';
        $vendorId = $data['vendor_id'] ?? null;
        $order = null;

        if (! empty($data['purchase_order_id'])) {
            $order = PurchaseOrder::with([
                'demand.lines',
                'lines.receiptLines',
                'receipts.lines.demandLine',
            ])->findOrFail($data['purchase_order_id']);

            if ($order->receipts->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => 'The goods have not been verified as received yet. A bill cannot be entered before receipt.',
                ]);
            }

            $approvedAmount = $order->demand ? Money::of($order->demand->total_amount) : null;
            $orderedAmount = Money::of($order->order_amount);
            $vendorId = $order->vendor_id;

            // Calculate actual monetary value of received goods
            foreach ($order->lines as $pLine) {
                $recQty = $pLine->totalReceivedQty();
                $receivedAmount = Money::add($receivedAmount, Money::mul($pLine->unit_price, $recQty));
            }
        }

        if (! $vendorId) {
            if (empty($data['vendor_name'])) {
                throw ValidationException::withMessages(['vendor_name' => 'Name the vendor.']);
            }

            $vendorId = Vendor::firstOrCreate(['name' => trim($data['vendor_name'])])->id;
        }

        // Check for duplicate bill for THIS vendor (Prompt Section 19)
        $duplicate = Bill::where('vendor_id', $vendorId)->where('bill_no', $billNo)->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'bill_no' => sprintf(
                    'Bill number %s from this vendor is already on record, entered %s. The same bill cannot be claimed twice.',
                    $billNo,
                    $duplicate->entered_at->toDateString(),
                ),
            ]);
        }

        $billed = Money::of($data['bill_amount']);

        // Prepare line items
        $linesToCreate = [];
        $lineMatchFailed = false;

        if (! empty($data['lines'])) {
            $poLinesById = $order ? $order->lines->keyBy('id') : collect();

            foreach ($data['lines'] as $lineInput) {
                $qty = (int) $lineInput['quantity'];
                if ($qty <= 0) {
                    continue;
                }

                $poLine = ! empty($lineInput['purchase_order_line_id'])
                    ? $poLinesById->get($lineInput['purchase_order_line_id'])
                    : null;

                $unitPrice = Money::of($lineInput['unit_price']);
                $discount = isset($lineInput['discount']) ? Money::of($lineInput['discount']) : '0.00';
                $tax = isset($lineInput['tax']) ? Money::of($lineInput['tax']) : '0.00';
                $lineTotal = Money::add(Money::sub(Money::mul($unitPrice, $qty), $discount), $tax);

                // Line-level matching check
                if ($poLine) {
                    // Check price variance
                    if (! Money::eq($unitPrice, $poLine->unit_price)) {
                        $lineMatchFailed = true;
                    }

                    // Check quantity variance (invoiced qty <= accepted received qty)
                    $receivedQty = $poLine->totalReceivedQty();
                    if ($qty > $receivedQty) {
                        $lineMatchFailed = true;
                    }
                } elseif ($order) {
                    $lineMatchFailed = true;
                }

                $linesToCreate[] = [
                    'purchase_order_line_id' => $poLine?->id,
                    'item_type_id' => $lineInput['item_type_id'] ?? $poLine?->item_type_id,
                    'description' => $lineInput['description'] ?? $poLine?->description ?? 'Invoice item',
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'tax' => $tax,
                    'line_total' => $lineTotal,
                ];
            }
        } elseif ($order) {
            // Auto-populate from PO lines that were received
            foreach ($order->lines as $poLine) {
                $recQty = $poLine->totalReceivedQty();
                if ($recQty <= 0) {
                    continue;
                }

                $lineTotal = Money::mul($poLine->unit_price, $recQty);

                $linesToCreate[] = [
                    'purchase_order_line_id' => $poLine->id,
                    'item_type_id' => $poLine->item_type_id,
                    'description' => $poLine->description,
                    'quantity' => $recQty,
                    'unit_price' => $poLine->unit_price,
                    'discount' => '0.00',
                    'tax' => '0.00',
                    'line_total' => $lineTotal,
                ];
            }
        }

        // Fallback for direct bill without order or lines: create single line representing the bill
        if (empty($linesToCreate)) {
            $linesToCreate[] = [
                'purchase_order_line_id' => null,
                'item_type_id' => null,
                'description' => "Bill {$billNo}",
                'quantity' => 1,
                'unit_price' => $billed,
                'discount' => '0.00',
                'tax' => Money::of($data['vat_amount'] ?? 0),
                'line_total' => $billed,
            ];
        }

        // Total variance
        $variance = $orderedAmount === null
            ? '0.00'
            : Money::sub($billed, $receivedAmount);

        // Matches if:
        // 1. Attached to a PO with goods receipt
        // 2. Line items match rate and quantity received
        // 3. Billed amount equals received amount and <= approved amount
        $matched = $orderedAmount !== null
            && ! $lineMatchFailed
            && Money::isZero($variance)
            && ($approvedAmount === null || Money::lte($billed, $approvedAmount));

        return DB::transaction(function () use ($data, $billNo, $vendorId, $billed, $approvedAmount, $orderedAmount, $variance, $matched, $linesToCreate, $user) {
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
                'payment_status' => 'UNPAID',
                'paid_amount' => '0.00',
                'attachment_path' => $data['attachment_path'] ?? null,
                'entered_by_id' => $user->id,
            ]);

            foreach ($linesToCreate as $lData) {
                $bill->lines()->create($lData);
            }

            $bill->load(['vendor', 'lines']);

            // Post double-entry bill accounting
            $this->accounting->recordBill($bill);

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

            if ($bill->match_status !== MatchStatus::MATCHED) {
                $this->notify->billFlagged($bill, $user);
            }

            return $bill;
        });
    }

    public function list(?MatchStatus $status = null, ?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        return Bill::query()
            ->when($status, fn ($q) => $q->where('match_status', $status))
            ->when($search, function ($q, $search) {
                $search = trim($search);
                $q->where(function ($sub) use ($search) {
                    $sub->where('bill_no', 'like', "%{$search}%")
                        ->orWhere('variance_note', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseOrder', fn ($po) => $po->where('ref', 'like', "%{$search}%"))
                        ->orWhereHas('enteredBy', fn ($u) => $u->where('full_name', 'like', "%{$search}%"));
                });
            })
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
        return collect(DB::select(
            'SELECT * FROM v_three_way_match WHERE tenant_id = ? ORDER BY bill_date DESC',
            [$this->tenant->idOrFail()],
        ));
    }

    /**
     * Accounts accepts a difference.
     * Separation of duties: The person who entered the bill CANNOT clear its variance.
     */
    public function clearVariance(string $billId, string $note, User $user): Bill
    {
        $bill = Bill::with('vendor')->findOrFail($billId);

        if ($bill->match_status !== MatchStatus::MISMATCH) {
            throw ValidationException::withMessages([
                'bill' => 'This bill is not flagged, so there is nothing to clear.',
            ]);
        }

        if ($bill->entered_by_id === $user->id) {
            throw new AuthorizationException(
                'You entered this bill. Separation of duties requires another user to review and accept the variance.'
            );
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
