<?php

namespace App\Livewire\Bills;

use App\Enums\MatchStatus;
use App\Models\Bill;
use App\Services\BillService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The three-way match: approved against ordered against billed, computed live
 * from the source rows rather than stored anywhere.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

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

        $msg = "The variance on bill {$bill->bill_no} is accepted and on record against your name. The original three figures are unchanged.";
        session()->flash('status', $msg);
        $this->dispatch('toast', message: $msg, tone: 'ok', title: 'Variance Cleared');
    }

    public function render(BillService $bills): View
    {
        return view('livewire.bills.index', [
            'bills' => $bills->list(
                $this->status ? MatchStatus::from($this->status) : null,
                $this->search ?: null,
            ),
            'awaitingBill' => $bills->awaitingBill(),
            // Fetched by id rather than picked out of the page: the bill being
            // cleared may well be on a page the list is no longer showing.
            'clearing' => $this->clearingId ? Bill::with('vendor:id,name')->find($this->clearingId) : null,
        ])->title('Bills');
    }

    public function exportCsv(): StreamedResponse
    {
        $status = $this->status ? MatchStatus::from($this->status) : null;
        $search = $this->search ?: null;

        $items = Bill::query()
            ->when($status, fn ($q) => $q->where('match_status', $status))
            ->when($search, function ($q, $search) {
                $search = trim($search);
                $q->where(function ($sub) use ($search) {
                    $sub->where('bill_no', 'like', "%{$search}%")
                        ->orWhere('variance_note', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseOrder', fn ($po) => $po->where('ref', 'like', "%{$search}%"))
                        ->orWhereHas('enteredBy', fn ($u) => $u->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->with(['vendor:id,name', 'purchaseOrder:id,ref', 'enteredBy:id,full_name'])
            ->orderByDesc('entered_at')
            ->limit(5000)
            ->get();

        $fileName = 'bills_report_'.now()->format('Y_m_d_His').'.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Bill No', 'Vendor', 'Fiscal Year', 'PO Ref', 'Bill Date', 'Entered Date', 'Match Status', 'Payment Status', 'Bill Amount (NPR)', 'Paid Amount (NPR)', 'Variance Amount (NPR)', 'Entered By']);

            foreach ($items as $b) {
                fputcsv($handle, [
                    $b->bill_no,
                    $b->vendor?->name ?? 'N/A',
                    $b->fiscal_year,
                    $b->purchaseOrder?->ref ?? 'Direct',
                    $b->bill_date?->toDateString() ?? '',
                    $b->entered_at?->toDateString() ?? '',
                    $b->match_status->value ?? '',
                    $b->payment_status,
                    $b->bill_amount,
                    $b->paid_amount,
                    $b->variance_amount,
                    $b->enteredBy?->full_name ?? '',
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv']);
    }
}
