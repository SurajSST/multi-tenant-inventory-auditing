<?php

namespace App\Livewire\Bills;

use App\Enums\PaymentMethod;
use App\Models\Bill;
use App\Models\Vendor;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Pay extends Component
{
    public string $vendorId = '';

    public string $paymentMethod = 'BANK_TRANSFER';

    public string $paymentDate = '';

    public string $amount = '';

    public string $referenceNo = '';

    public string $bankName = '';

    public string $remarks = '';

    /** billId => allocated amount string */
    public array $allocations = [];

    public function mount(?string $billId = null): void
    {
        $this->paymentDate = now()->toDateString();
        $billId = $billId ?: request()->query('billId');

        if ($billId) {
            $bill = Bill::find($billId);
            if ($bill) {
                $this->vendorId = $bill->vendor_id;
                $this->allocations[$bill->id] = (string) $bill->remainingBalance();
                $this->amount = (string) $bill->remainingBalance();
            }
        }
    }

    #[Computed]
    public function vendors(): Collection
    {
        return Vendor::orderBy('name')->get();
    }

    #[Computed]
    public function payableBills(): Collection
    {
        if (! $this->vendorId) {
            return collect();
        }

        return app(PaymentService::class)->payableBills($this->vendorId);
    }

    public function updatedVendorId(): void
    {
        $this->allocations = [];
        $this->amount = '';
    }

    /** Auto-fill allocation when payment amount changes */
    public function autoAllocate(): void
    {
        if (! $this->amount || Money::lte($this->amount, 0)) {
            return;
        }

        $remainingToAllocate = Money::of($this->amount);
        $this->allocations = [];

        foreach ($this->payableBills as $bill) {
            if (Money::lte($remainingToAllocate, 0)) {
                break;
            }

            $billBalance = $bill->remainingBalance();
            $alloc = Money::min($remainingToAllocate, $billBalance);

            $this->allocations[$bill->id] = (string) $alloc;
            $remainingToAllocate = Money::sub($remainingToAllocate, $alloc);
        }
    }

    public function save(PaymentService $payments): void
    {
        $this->validate([
            'vendorId' => ['required', 'string', 'exists:vendors,id'],
            'paymentMethod' => ['required', Rule::enum(PaymentMethod::class)],
            'paymentDate' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'referenceNo' => ['nullable', 'string', 'max:100'],
            'bankName' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $preparedAllocations = [];
        foreach ($this->allocations as $bId => $allocAmount) {
            if ($allocAmount && Money::gt($allocAmount, 0)) {
                $preparedAllocations[] = [
                    'bill_id' => $bId,
                    'allocated_amount' => Money::of($allocAmount),
                ];
            }
        }

        if (empty($preparedAllocations)) {
            $this->addError('amount', 'Allocate the payment amount to at least one outstanding bill.');

            return;
        }

        $payment = $payments->recordPayment([
            'vendor_id' => $this->vendorId,
            'payment_method' => $this->paymentMethod,
            'payment_date' => $this->paymentDate,
            'amount' => $this->amount,
            'reference_no' => $this->referenceNo ?: null,
            'bank_name' => $this->bankName ?: null,
            'remarks' => $this->remarks ?: null,
            'allocations' => $preparedAllocations,
        ], auth()->user());

        session()->flash('flash.banner', sprintf(
            'Payment voucher %s for %s recorded successfully.',
            $payment->voucher_no,
            Money::npr($payment->amount)
        ));

        $this->redirect(route('bills.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.bills.pay');
    }
}
