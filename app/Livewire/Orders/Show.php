<?php

namespace App\Livewire\Orders;

use App\Models\PurchaseOrder;
use App\Services\OrderService;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public string $orderId;

    public function mount(PurchaseOrder $order): void
    {
        $this->orderId = $order->id;
    }

    public function render(OrderService $orders): View
    {
        $order = $orders->find($this->orderId);

        return view('livewire.orders.show', ['order' => $order])->title($order->ref);
    }
}
