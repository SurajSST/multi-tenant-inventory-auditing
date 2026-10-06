<?php

namespace App\Livewire\Payments;

use App\Services\PaymentService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        return app(PaymentService::class)->list(20);
    }

    public function render(): View
    {
        return view('livewire.payments.index');
    }
}
