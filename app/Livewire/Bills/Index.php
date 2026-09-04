<?php

namespace App\Livewire\Bills;

use App\Enums\MatchStatus;
use App\Models\Bill;
use App\Services\BillService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The three-way match: approved against ordered against billed, computed live
 * from the source rows rather than stored anywhere.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    public ?string $clearingId = null;

    public string $varianceNote = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function openClear(string $billId): void
    {
        $this->clearingId = $billId;
        $this->varianceNote = '';
        $this->resetErrorBag();
    }

    public function closeClear(): void
    {
        $this->reset(['clearingId', 'varianceNote']);
    }

    public function clear(BillService $bills): void
    {
        $bill = $bills->clearVariance($this->clearingId, $this->varianceNote, auth()->user());

        $this->closeClear();

        session()->flash('status', "The variance on bill {$bill->bill_no} is accepted and on record against your name. ".
            'The original three figures are unchanged.');
    }

    public function render(BillService $bills): View
    {
        return view('livewire.bills.index', [
            'bills' => $bills->list($this->status ? MatchStatus::from($this->status) : null),
            'awaitingBill' => $bills->awaitingBill(),
            // Fetched by id rather than picked out of the page: the bill being
            // cleared may well be on a page the list is no longer showing.
            'clearing' => $this->clearingId ? Bill::with('vendor:id,name')->find($this->clearingId) : null,
        ])->title('Bills');
    }
}
