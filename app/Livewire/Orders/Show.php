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
        $user = auth()->user();

        abort_unless(
            $user->seesEverything()
            || $order->ordered_by_id === $user->id
            || ($order->demand && $order->demand->raised_by_id === $user->id)
            || $user->can('receive-goods')
            || $user->approval_tier > 0,
            403,
            'You are not authorized to view this purchase order.'
        );

        return view('livewire.orders.show', ['order' => $order])->title($order->ref);
    }
}
