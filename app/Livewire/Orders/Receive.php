<?php

namespace App\Livewire\Orders;

use App\Enums\ReceiptCondition;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Services\OrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Verifying that goods arrived. This is the second half of the separation of
 * duties: the person who placed the order is refused here, by this component,
 * by the service, and finally by the database itself.
 */
class Receive extends Component
{
    use WithFileUploads;

    public string $orderId;

    public string $locationId = '';

    public string $condition = ReceiptCondition::GOOD->value;

    public string $challanNo = '';

    public string $discrepancyNote = '';

    public $photo;

    /** demand line id => quantity received */
    public array $received = [];

    public array $remarks = [];

    public function mount(PurchaseOrder $order): void
    {
        $this->orderId = $order->id;

        // Pre-fill with the ordered quantity — the common case is that
        // everything arrived, and short supply is the exception worth typing.
        $this->received = $this->order->demand->lines
            ->mapWithKeys(fn ($line) => [$line->id => (string) $line->quantity])
            ->all();

        $this->locationId = Location::active()->orderBy('name')->first()?->id ?? '';
    }

    #[Computed]
    public function order(): PurchaseOrder
    {
        return app(OrderService::class)->find($this->orderId);
    }

    #[Computed]
    public function blocks(): Collection
    {
        return Location::active()->orderBy('name')->get();
    }

    /** True when at least one line is short of what was ordered. */
    #[Computed]
    public function isShort(): bool
    {
        return $this->order->demand->lines->contains(
            fn ($line) => (int) ($this->received[$line->id] ?? 0) < $line->quantity
        );
    }

    public function save(OrderService $orders): void
    {
        $this->validate([
            'locationId' => ['required', 'string', 'exists:locations,id'],
            'condition' => ['required', Rule::enum(ReceiptCondition::class)],
            'challanNo' => ['nullable', 'string', 'max:60'],
            'discrepancyNote' => ['nullable', 'string', 'max:1000'],
            'received.*' => ['required', 'integer', 'min:0'],
            'photo' => ['nullable', 'image', 'max:'.config('prativa.attachments.max_kb')],
        ], [], ['received.*' => 'quantity received']);

        if ($this->isShort && ! trim($this->discrepancyNote)) {
            $this->addError('discrepancyNote', 'Less arrived than was ordered. Say what was short and why.');

            return;
        }

        $lines = $this->order->demand->lines->map(fn ($line) => [
            'demand_line_id' => $line->id,
            'qty_received' => (int) ($this->received[$line->id] ?? 0),
            'remark' => $this->remarks[$line->id] ?? null,
        ])->all();

        $path = $this->photo?->store(
            config('prativa.attachments.directory').'/receipts',
            config('prativa.attachments.disk')
        );

        try {
            $result = $orders->receive($this->orderId, $lines, [
                'location_id' => $this->locationId,
                'condition' => ReceiptCondition::from($this->condition),
                'discrepancy_note' => $this->discrepancyNote ?: null,
                'challan_no' => $this->challanNo ?: null,
                'attachment_path' => $path,
            ], auth()->user());
        } catch (AuthorizationException $e) {
            $this->addError('receipt', $e->getMessage());

            return;
        }

        session()->flash('status', sprintf(
            '%s verified. %d unit(s) posted into %s.%s',
            $this->order->ref,
            $result['units_posted'],
            $this->blocks->firstWhere('id', $this->locationId)->name,
            $result['partial'] ? ' The order is marked partly received.' : '',
        ));

        $this->redirectRoute('orders.show', $this->orderId, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.orders.receive')->title('Verify Receipt');
    }
}
