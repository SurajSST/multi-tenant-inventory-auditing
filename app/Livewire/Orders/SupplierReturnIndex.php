<?php

namespace App\Livewire\Orders;

use App\Services\SupplierReturnService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class SupplierReturnIndex extends Component
{
    use WithPagination;

    #[Computed]
    public function returns(): LengthAwarePaginator
    {
        return app(SupplierReturnService::class)->list(perPage: 20);
    }

    public function render(): View
    {
        return view('livewire.orders.supplier-return-index')->title('Supplier Returns');
    }
}
