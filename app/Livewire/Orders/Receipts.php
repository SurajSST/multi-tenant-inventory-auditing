<?php

namespace App\Livewire\Orders;

use App\Models\GoodsReceipt;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Receipts extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $search = trim($this->search);

        return view('livewire.orders.receipts', [
            'receipts' => GoodsReceipt::query()
                ->with([
                    'purchaseOrder:id,ref,vendor_id,demand_id,ordered_by_id,ordered_at',
                    'purchaseOrder.vendor:id,name',
                    'purchaseOrder.demand:id,ref',
                    'location:id,name',
                    'receivedBy:id,full_name',
                ])
                ->when($search, fn ($query) => $query->whereHas('purchaseOrder', fn ($order) => $order
                    ->where('ref', 'like', '%'.$search.'%')
                    ->orWhereHas('vendor', fn ($vendor) => $vendor->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('demand', fn ($demand) => $demand->where('ref', 'like', '%'.$search.'%'))))
                ->orderByDesc('received_at')
                ->paginate(25),
        ])->title('Goods Receipts');
    }
}
