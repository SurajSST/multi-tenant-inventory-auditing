<?php

namespace App\Livewire\Orders;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\SupplierReturnService;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SupplierReturnCreate extends Component
{
    public string $orderId;

    public string $receiptId = '';

    public string $reason = '';

    /** goods receipt line id => quantity to return */
    public array $returnQty = [];

    public array $lineReasons = [];

    public function mount(PurchaseOrder $order): void
    {
        $this->orderId = $order->id;
        $order->loadMissing(['receipts.lines.demandLine', 'receipts.lines.poLine', 'receipts.lines.location', 'vendor']);

        $firstReceipt = $order->receipts->first();
        if ($firstReceipt) {
            $this->receiptId = $firstReceipt->id;
            $this->initLines($firstReceipt);
        }
    }

    public function updatedReceiptId(): void
    {
        $receipt = GoodsReceipt::with(['lines.demandLine', 'lines.poLine', 'lines.location'])->find($this->receiptId);
        if ($receipt) {
            $this->initLines($receipt);
        }
    }

    private function initLines(GoodsReceipt $receipt): void
    {
        $this->returnQty = [];
        $this->lineReasons = [];
        foreach ($receipt->lines as $line) {
            $this->returnQty[$line->id] = 0;
            $this->lineReasons[$line->id] = '';
        }
    }

    #[Computed]
    public function order(): PurchaseOrder
    {
        return PurchaseOrder::with(['vendor', 'receipts.lines.demandLine', 'receipts.lines.poLine', 'receipts.lines.location'])->findOrFail($this->orderId);
    }

    #[Computed]
    public function receipt(): ?GoodsReceipt
    {
        return $this->receiptId ? $this->order->receipts->firstWhere('id', $this->receiptId) : null;
    }

    public function save(SupplierReturnService $returns): void
    {
        $this->validate([
            'receiptId' => ['required', 'string', 'exists:goods_receipts,id'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'returnQty.*' => ['required', 'integer', 'min:0'],
        ]);

        $lines = [];
        $totalQty = 0;

        if ($this->receipt) {
            foreach ($this->receipt->lines as $line) {
                $qty = (int) ($this->returnQty[$line->id] ?? 0);
                if ($qty > 0) {
                    $totalQty += $qty;
                    $lines[] = [
                        'goods_receipt_line_id' => $line->id,
                        'quantity' => $qty,
                        'reason' => ! empty($this->lineReasons[$line->id]) ? $this->lineReasons[$line->id] : $this->reason,
                    ];
                }
            }
        }

        if ($totalQty === 0) {
            $this->addError('returnQty', 'At least one line item must have a return quantity greater than zero.');

            return;
        }

        $return = $returns->create([
            'goods_receipt_id' => $this->receiptId,
            'reason' => $this->reason,
            'lines' => $lines,
        ], auth()->user());

        session()->flash('status', "Supplier return {$return->ref} posted successfully. Inventory and Accounts Payable updated.");

        $this->redirectRoute('orders.show', $this->orderId, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.orders.supplier-return-create')->title('Return Goods to Supplier');
    }
}
