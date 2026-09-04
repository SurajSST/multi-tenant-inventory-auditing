<?php

namespace App\Livewire\Orders;

use App\Models\DemandForm;
use App\Models\Vendor;
use App\Services\OrderService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Placing the order. Ordering above the approved amount is allowed but never
 * silent: it is flagged here, written into the audit trail, and the bill will
 * not match until Accounts accepts the difference in writing.
 */
class Create extends Component
{
    #[Url]
    public string $demandId = '';

    public string $vendorId = '';

    public string $vendorName = '';

    public string $vendorPanVat = '';

    public string $orderAmount = '';

    public string $expectedDate = '';

    public string $note = '';

    #[Computed]
    public function awaiting(): Collection
    {
        return app(OrderService::class)->awaitingOrder();
    }

    #[Computed]
    public function demand(): ?DemandForm
    {
        return $this->demandId ? $this->awaiting->firstWhere('id', $this->demandId) : null;
    }

    #[Computed]
    public function vendors(): Collection
    {
        return Vendor::active()->orderBy('name')->get();
    }

    /** How far the order sits above what was approved, if at all. */
    #[Computed]
    public function overApprovedBy(): string
    {
        if (! $this->demand || $this->orderAmount === '') {
            return '0.00';
        }

        $over = Money::sub($this->orderAmount, $this->demand->total_amount);

        return Money::gt($over, 0) ? $over : '0.00';
    }

    public function updatedDemandId(): void
    {
        unset($this->demand);

        if ($this->demand) {
            $this->orderAmount = (string) $this->demand->total_amount;
        }
    }

    public function save(OrderService $orders): void
    {
        $this->validate([
            'demandId' => ['required', 'string', 'exists:demand_forms,id'],
            'vendorId' => ['nullable', 'string', 'exists:vendors,id'],
            'vendorName' => ['required_without:vendorId', 'nullable', 'string', 'max:180'],
            'vendorPanVat' => ['nullable', 'string', 'max:40'],
            'orderAmount' => ['required', 'numeric', 'gt:0'],
            'expectedDate' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'vendorName.required_without' => 'Pick a vendor, or type a new one.',
        ]);

        $order = $orders->create([
            'demand_id' => $this->demandId,
            'vendor_id' => $this->vendorId ?: null,
            'vendor_name' => $this->vendorName ?: null,
            'vendor_pan_vat' => $this->vendorPanVat ?: null,
            'order_amount' => $this->orderAmount,
            'expected_date' => $this->expectedDate ?: null,
            'note' => $this->note ?: null,
        ], auth()->user());

        session()->flash('status', "{$order->ref} placed with {$order->vendor->name}. ".
            'Somebody other than you must verify the goods when they arrive.');

        $this->redirectRoute('orders.show', $order, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.orders.create')->title('Place an Order');
    }
}
