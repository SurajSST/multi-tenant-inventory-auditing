<?php

namespace App\Livewire\Bills;

use App\Models\Vendor;
use App\Services\BillService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Entering a bill. A bill cannot be entered before the goods have been verified
 * as received, and the same bill number can never appear twice anywhere in the
 * system — that is what blocks the double claim.
 */
class Create extends Component
{
    use WithFileUploads;

    #[Url]
    public string $purchaseOrderId = '';

    public string $billNo = '';

    public string $billDate = '';

    public string $billAmount = '';

    public string $vatAmount = '';

    public string $vendorId = '';

    public string $vendorName = '';

    public $scan;

    public function mount(): void
    {
        $this->billDate = now()->toDateString();
    }

    #[Computed]
    public function awaiting(): Collection
    {
        return app(BillService::class)->awaitingBill();
    }

    #[Computed]
    public function order(): ?object
    {
        return $this->purchaseOrderId ? $this->awaiting->firstWhere('id', $this->purchaseOrderId) : null;
    }

    #[Computed]
    public function vendors(): Collection
    {
        return Vendor::active()->orderBy('name')->get();
    }

    /** What the bill will be judged against, shown before it is saved. */
    #[Computed]
    public function willMatch(): ?bool
    {
        if (! $this->order || $this->billAmount === '') {
            return null;
        }

        return Money::eq($this->billAmount, $this->order->order_amount)
            && Money::lte($this->billAmount, $this->order->demand->total_amount);
    }

    public function updatedPurchaseOrderId(): void
    {
        unset($this->order);

        if ($this->order) {
            $this->billAmount = (string) $this->order->order_amount;
        }
    }

    public function save(BillService $bills): void
    {
        $this->validate([
            'purchaseOrderId' => ['nullable', 'string', 'exists:purchase_orders,id'],
            'vendorId' => ['nullable', 'string', 'exists:vendors,id'],
            'billNo' => ['required', 'string', 'max:80'],
            'billDate' => ['required', 'date'],
            'billAmount' => ['required', 'numeric', 'gt:0'],
            'vatAmount' => ['nullable', 'numeric', 'min:0'],
            'vendorName' => ['required_without_all:vendorId,purchaseOrderId', 'nullable', 'string', 'max:180'],
            'scan' => ['nullable', 'file', 'max:'.config('prativa.attachments.max_kb'),
                'mimes:'.implode(',', config('prativa.attachments.mimes'))],
        ], [
            'vendorName.required_without_all' => 'Name the vendor, or attach this bill to a purchase order.',
        ]);

        $path = $this->scan?->store(
            config('prativa.attachments.directory').'/bills',
            config('prativa.attachments.disk')
        );

        $bill = $bills->create([
            'bill_no' => $this->billNo,
            'purchase_order_id' => $this->purchaseOrderId ?: null,
            'vendor_id' => $this->vendorId ?: null,
            'vendor_name' => $this->vendorName ?: null,
            'bill_date' => $this->billDate,
            'bill_amount' => $this->billAmount,
            'vat_amount' => $this->vatAmount ?: 0,
            'attachment_path' => $path,
        ], auth()->user());

        session()->flash('status', $bill->isFlagged()
            ? "Bill {$bill->bill_no} is entered but FLAGGED: it does not agree with the order. It stays flagged until it is cleared in writing."
            : "Bill {$bill->bill_no} is entered and matches the order and the approval.");

        $this->redirectRoute('bills.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.bills.create')->title('Enter a Bill');
    }
}
