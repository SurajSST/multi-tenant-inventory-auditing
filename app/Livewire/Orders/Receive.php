<?php

namespace App\Livewire\Orders;

use App\Enums\ReceiptCondition;
use App\Models\GoodsReceiptLine;
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
 * Verifying that goods arrived. Supports partial and cumulative shipments.
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

    /** line id => quantity received now */
    public array $received = [];

    /** line id => previously received */
    public array $priorReceived = [];

    /** line id => remaining quantity to receive */
    public array $remainingQty = [];

    public array $remarks = [];

    public function mount(PurchaseOrder $order): void
    {
        $this->orderId = $order->id;
        $order->loadMissing(['lines.demandLine', 'demand.lines']);

        $lines = $order->lines->isNotEmpty() ? $order->lines : ($order->demand?->lines ?? collect());

        if ($order->lines->isNotEmpty()) {
            foreach ($order->lines as $pLine) {
                $prior = $pLine->totalReceivedQty();
                $rem = $pLine->remainingQty();
                $this->priorReceived[$pLine->id] = $prior;
                $this->remainingQty[$pLine->id] = $rem;
                $this->received[$pLine->id] = (string) $rem;
            }
        } elseif ($order->demand) {
            $lineIds = $order->demand->lines->pluck('id');
            $priors = GoodsReceiptLine::whereIn('demand_line_id', $lineIds)
                ->groupBy('demand_line_id')
                ->selectRaw('demand_line_id, sum(qty_received) as total')
                ->pluck('total', 'demand_line_id');

            foreach ($order->demand->lines as $line) {
                $prior = (int) ($priors->get($line->id) ?? 0);
                $rem = max(0, $line->quantity - $prior);
                $this->priorReceived[$line->id] = $prior;
                $this->remainingQty[$line->id] = $rem;
                $this->received[$line->id] = (string) $rem;
            }
        }

        $this->locationId = Location::active()->orderBy('name')->first()?->id ?? '';
    }

    public function getReceivedQtyForLine($line): int
    {
        $demandLineId = $line->demand_line_id ?? null;
        if ($demandLineId && isset($this->received[$demandLineId])) {
            return (int) $this->received[$demandLineId];
        }

        return (int) ($this->received[$line->id] ?? 0);
    }

    #[Computed]
    public function order(): PurchaseOrder
    {
        return PurchaseOrder::with(['demand.lines.itemType', 'lines.demandLine.itemType', 'vendor'])->findOrFail($this->orderId);
    }

    #[Computed]
    public function displayLines(): Collection
    {
        return $this->order->lines->isNotEmpty() ? $this->order->lines : ($this->order->demand?->lines ?? collect());
    }

    #[Computed]
    public function blocks(): Collection
    {
        return Location::active()->orderBy('name')->get();
    }

    /** True when at least one line is short of what was ordered across all receipts */
    #[Computed]
    public function isShort(): bool
    {
        return $this->displayLines->contains(function ($line) {
            $ordered = $line->quantity_ordered ?? $line->quantity;
            $curr = $this->getReceivedQtyForLine($line);
            $prior = (int) ($this->priorReceived[$line->id] ?? 0);

            return ($prior + $curr) < $ordered;
        });
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

        foreach ($this->displayLines as $line) {
            $val = $this->getReceivedQtyForLine($line);
            $ordered = $line->quantity_ordered ?? $line->quantity;
            $maxAllowed = (int) ($this->remainingQty[$line->id] ?? $ordered);
            if ($val > $maxAllowed) {
                $name = $line->description ?? $line->item_name;
                $this->addError(
                    "received.{$line->id}",
                    "Cannot receive more than the remaining {$maxAllowed} units for {$name}."
                );

                return;
            }
        }

        if ($this->isShort && ! trim($this->discrepancyNote)) {
            $this->addError('discrepancyNote', 'Less arrived than was ordered. Say what was short and why.');

            return;
        }

        $lines = [];
        if ($this->order->lines->isNotEmpty()) {
            foreach ($this->order->lines as $poLine) {
                $val = $this->getReceivedQtyForLine($poLine);
                $lines[] = [
                    'purchase_order_line_id' => $poLine->id,
                    'qty_received' => $val,
                    'remark' => $this->remarks[$poLine->id] ?? ($poLine->demand_line_id ? ($this->remarks[$poLine->demand_line_id] ?? null) : null),
                ];
            }
        } elseif ($this->order->demand) {
            foreach ($this->order->demand->lines as $dLine) {
                $lines[] = [
                    'demand_line_id' => $dLine->id,
                    'qty_received' => (int) ($this->received[$dLine->id] ?? 0),
                    'remark' => $this->remarks[$dLine->id] ?? null,
                ];
            }
        }

        $path = $this->photo?->store(
            'attachments/receipts/'.now()->format('Y/m'),
            config('prativa.attachments.disk'),
        );

        try {
            $result = $orders->receive(
                orderId: $this->orderId,
                lines: $lines,
                meta: [
                    'location_id' => $this->locationId,
                    'condition' => ReceiptCondition::from($this->condition),
                    'challan_no' => $this->challanNo ?: null,
                    'discrepancy_note' => $this->discrepancyNote ?: null,
                    'attachment_path' => $path,
                ],
                user: auth()->user(),
            );

            session()->flash('flash.banner', sprintf(
                'Receipt recorded for %s (%d units posted to inventory)%s.',
                $this->order->ref,
                $result['units_posted'],
                $result['partial'] ? ' — partial shipment' : '',
            ));

            $this->redirect(route('orders.show', $this->orderId), navigate: true);
        } catch (AuthorizationException $e) {
            $this->addError('receipt', $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('livewire.orders.receive');
    }
}
