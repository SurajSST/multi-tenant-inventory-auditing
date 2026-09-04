<?php

namespace App\Livewire\Orders;

use App\Enums\OrderStatus;
use App\Services\OrderService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: false)]
    public bool $pendingReceipt = false;

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(OrderService $orders): View
    {
        return view('livewire.orders.index', [
            'orders' => $orders->list(
                $this->status ? OrderStatus::from($this->status) : null,
                $this->pendingReceipt,
            ),
            'awaitingOrder' => $orders->awaitingOrder(),
        ])->title('Purchase Orders');
    }
}
