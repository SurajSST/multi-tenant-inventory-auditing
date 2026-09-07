<?php

namespace App\Livewire\Bills;

use App\Models\PurchaseOrder;
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
 * Entering a bill with line items and true line-level 3-way matching.
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

    public array $billLines = [];

    public $scan;

    public function mount(): void
    {
        $this->billDate = now()->toDateString();
        if ($this->purchaseOrderId) {
            $this->updatedPurchaseOrderId();
        }
    }

    #[Computed]
    public function awaiting(): Collection
    {
        return app(BillService::class)->awaitingBill();
    }

    #[Computed]
    public function order(): ?object
    {
        if (! $this->purchaseOrderId) {
            return null;
        }

        return $this->awaiting->firstWhere('id', $this->purchaseOrderId)
            ?: PurchaseOrder::with(['demand.lines.itemType', 'lines.demandLine.itemType', 'vendor', 'receipts.lines', 'receipts.receivedBy'])->find($this->purchaseOrderId);
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
        if (! $this->order || ! $this->billAmount || Money::lte($this->billAmount, 0)) {
            return null;
        }

        // Must match accepted received value
        $receivedTotal = '0.00';
        if ($this->order->lines && $this->order->lines->isNotEmpty()) {
            foreach ($this->order->lines as $pLine) {
                $receivedTotal = Money::add($receivedTotal, Money::mul($pLine->unit_price, $pLine->totalReceivedQty()));
            }
        } elseif ($this->order->demand) {
            foreach ($this->order->demand->lines as $dLine) {
                $receivedTotal = Money::add($receivedTotal, Money::mul($dLine->unit_rate, $dLine->totalReceivedQty()));
            }
        }

        return Money::eq($this->billAmount, $receivedTotal)
            && ($this->order->demand ? Money::lte($this->billAmount, $this->order->demand->total_amount) : true);
    }

    public function updatedPurchaseOrderId(): void
    {
        unset($this->order);
        $this->billLines = [];

        if ($this->order) {
            $this->vendorId = $this->order->vendor_id;
            $total = '0.00';
            $poLines = $this->order->lines;
            if ($poLines && $poLines->isNotEmpty()) {
                foreach ($poLines as $pLine) {
                    $recQty = $pLine->totalReceivedQty();
                    if ($recQty > 0) {
                        $this->billLines[] = [
                            'purchase_order_line_id' => $pLine->id,
                            'item_type_id' => $pLine->item_type_id,
                            'description' => $pLine->description,
                            'received_qty' => $recQty,
                            'po_unit_price' => (string) $pLine->unit_price,
                            'quantity' => $recQty,
                            'unit_price' => (string) $pLine->unit_price,
                            'discount' => '0.00',
                            'tax' => '0.00',
                        ];
                        $total = Money::add($total, Money::mul($pLine->unit_price, $recQty));
                    }
                }
            } elseif ($this->order->demand && $this->order->demand->lines->isNotEmpty()) {
                foreach ($this->order->demand->lines as $dLine) {
                    $recQty = $dLine->totalReceivedQty();
                    if ($recQty > 0) {
                        $this->billLines[] = [
                            'purchase_order_line_id' => null,
                            'item_type_id' => $dLine->item_type_id,
                            'description' => $dLine->item_name,
                            'received_qty' => $recQty,
                            'po_unit_price' => (string) $dLine->unit_rate,
                            'quantity' => $recQty,
                            'unit_price' => (string) $dLine->unit_rate,
                            'discount' => '0.00',
                            'tax' => '0.00',
                        ];
                        $total = Money::add($total, Money::mul($dLine->unit_rate, $recQty));
                    }
                }
            }
            $this->billAmount = $total;
        }
    }

    public function updatedBillLines(): void
    {
        $this->recalculateBillAmount();
    }

    public function recalculateBillAmount(): void
    {
        $total = '0.00';
        foreach ($this->billLines as $l) {
            $qty = (int) ($l['quantity'] ?? 0);
            $rate = Money::of($l['unit_price'] ?? 0);
            $disc = Money::of($l['discount'] ?? 0);
            $tax = Money::of($l['tax'] ?? 0);
            if ($qty > 0) {
                $sub = Money::add(Money::sub(Money::mul($rate, $qty), $disc), $tax);
                $total = Money::add($total, $sub);
            }
        }
        $this->billAmount = $total;
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
            'billLines.*.quantity' => ['required', 'integer', 'min:1'],
            'billLines.*.unit_price' => ['required', 'numeric', 'min:0'],
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
            'lines' => $this->billLines,
        ], auth()->user());

        session()->flash('status', $bill->isFlagged()
            ? "Bill {$bill->bill_no} is entered but FLAGGED: it does not agree with the order/receipts. It stays flagged until it is cleared in writing."
            : "Bill {$bill->bill_no} is entered and matches the order and the approval.");

        $this->redirectRoute('bills.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.bills.create')->title('Enter a Bill');
    }
}
