<?php

namespace App\Services;

use App\Enums\MatchStatus;
use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Money;
use App\Support\RefCounter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private AuditLogger $audit,
        private AccountingService $accounting,
        private Notifier $notify,
    ) {}

    /**
     * Outstanding bills eligible for payment.
     */
    public function payableBills(?string $vendorId = null): Collection
    {
        return Bill::query()
            ->where(function ($q) {
                $q->whereIn('payment_status', ['UNPAID', 'PART_PAID'])
                    ->orWhereNull('payment_status');
            })
            ->whereIn('match_status', [MatchStatus::MATCHED, MatchStatus::VARIANCE_CLEARED])
            ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
            ->with(['vendor:id,name', 'purchaseOrder:id,ref'])
            ->orderBy('bill_date')
            ->get();
    }

    /**
     * Record a disbursement against vendor bills with double-entry accounting.
     *
     * @param array{
     *     vendor_id: string,
     *     payment_method: PaymentMethod|string,
     *     payment_date: string,
     *     amount: string|float,
     *     reference_no?: string|null,
     *     bank_name?: string|null,
     *     remarks?: string|null,
     *     allocations: array<array{bill_id: string, allocated_amount: string|float}>
     * } $data
     */
    public function recordPayment(array $data, User $user): Payment
    {
        $amount = Money::of($data['amount']);

        if (Money::lte($amount, 0)) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        $vendor = Vendor::findOrFail($data['vendor_id']);
        $allocations = $data['allocations'] ?? [];

        if (empty($allocations)) {
            throw ValidationException::withMessages([
                'allocations' => 'At least one bill must be allocated for payment.',
            ]);
        }

        // Verify total allocated does not exceed payment amount
        $totalAllocated = '0.00';
        $billsToUpdate = [];

        foreach ($allocations as $alloc) {
            $allocAmount = Money::of($alloc['allocated_amount']);
            if (Money::lte($allocAmount, 0)) {
                continue;
            }

            /** @var Bill $bill */
            $bill = Bill::where('vendor_id', $vendor->id)->findOrFail($alloc['bill_id']);

            if (! in_array($bill->match_status, [MatchStatus::MATCHED, MatchStatus::VARIANCE_CLEARED])) {
                throw ValidationException::withMessages([
                    'allocations' => sprintf(
                        'Bill %s has an uncleared variance. A mismatch must be resolved before disbursement.',
                        $bill->bill_no
                    ),
                ]);
            }

            $remaining = $bill->remainingBalance();
            if (Money::gt($allocAmount, $remaining)) {
                throw ValidationException::withMessages([
                    'allocations' => sprintf(
                        'Allocated amount %s for bill %s exceeds remaining unpaid balance of %s.',
                        Money::npr($allocAmount),
                        $bill->bill_no,
                        Money::npr($remaining)
                    ),
                ]);
            }

            $totalAllocated = Money::add($totalAllocated, $allocAmount);
            $billsToUpdate[] = [
                'bill' => $bill,
                'allocated' => $allocAmount,
            ];
        }

        if (Money::ne($totalAllocated, $amount)) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Total allocations (%s) do not match payment amount (%s). Full allocation is required.',
                    Money::npr($totalAllocated),
                    Money::npr($amount)
                ),
            ]);
        }

        $paymentMethod = is_string($data['payment_method'])
            ? PaymentMethod::from($data['payment_method'])
            : $data['payment_method'];

        return DB::transaction(function () use ($data, $amount, $vendor, $billsToUpdate, $paymentMethod, $user) {
            ['ref' => $voucherNo, 'fiscal_year' => $fiscalYear] = RefCounter::next('PV');

            $payment = Payment::create([
                'voucher_no' => $voucherNo,
                'fiscal_year' => $fiscalYear,
                'vendor_id' => $vendor->id,
                'payment_method' => $paymentMethod,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'reference_no' => $data['reference_no'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'paid_by_id' => $user->id,
            ]);

            foreach ($billsToUpdate as $item) {
                /** @var Bill $bill */
                $bill = $item['bill'];
                $allocated = $item['allocated'];

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'bill_id' => $bill->id,
                    'amount_allocated' => $allocated,
                ]);

                $newPaid = Money::add($bill->paid_amount, $allocated);
                $newStatus = Money::gte($newPaid, $bill->bill_amount) ? 'PAID' : 'PART_PAID';

                $bill->update([
                    'paid_amount' => $newPaid,
                    'payment_status' => $newStatus,
                ]);
            }

            // Post double-entry journal (Dr Accounts Payable, Cr Bank / Cash)
            $this->accounting->recordPayment($payment);

            $this->audit->record(
                action: 'PAYMENT_RECORDED',
                entity: 'payments',
                entityId: $payment->id,
                detail: sprintf(
                    'Disbursement voucher %s of %s to %s via %s by %s',
                    $voucherNo,
                    Money::npr($amount),
                    $vendor->name,
                    $paymentMethod->value,
                    $user->full_name
                ),
                actor: $user,
                after: [
                    'voucher_no' => $voucherNo,
                    'amount' => (string) $amount,
                    'vendor' => $vendor->name,
                    'method' => $paymentMethod->value,
                ]
            );

            return $payment;
        });
    }

    public function list(int $perPage = 25): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['vendor:id,name', 'paidBy:id,full_name', 'allocations.bill:id,bill_no'])
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function find(string $id): Payment
    {
        return Payment::with(['vendor', 'paidBy', 'allocations.bill'])->findOrFail($id);
    }
}
