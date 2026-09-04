<?php

namespace App\Livewire\Demands;

use App\Models\ItemType;
use App\Services\DemandService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Raising a demand form. The total and the tier it will have to reach are shown
 * as the requester types, so nobody is surprised by where their request goes.
 */
class Create extends Component
{
    public string $department = '';

    public string $justification = '';

    public string $needByDate = '';

    /** @var array<int, array{row_id: string, item_type_id: string, item_name: string, quantity: string, unit_rate: string, specification: string}> */
    public array $lines = [];

    public function mount(): void
    {
        $this->department = (string) (auth()->user()?->designation ?? '');
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'row_id' => Str::random(8),
            'item_type_id' => '',
            'item_name' => '',
            'quantity' => '1',
            'unit_rate' => '',
            'specification' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);

        if (! $this->lines) {
            $this->addLine();
        }
    }

    /** Picking a known item fills in its name and last known rate. */
    public function updatedLines($value, string $key): void
    {
        $parts = explode('.', $key);

        if (count($parts) < 2) {
            return;
        }

        [$index, $field] = $parts;
        $index = (int) $index;

        if ($field !== 'item_type_id' || ! isset($this->lines[$index])) {
            return;
        }

        if (! $value) {
            return;
        }

        $item = ItemType::find($value);

        if (! $item) {
            return;
        }

        $this->lines[$index]['item_name'] = $item->name;

        if ($item->indicative_rate) {
            $this->lines[$index]['unit_rate'] = (string) $item->indicative_rate;
        }
    }

    #[Computed]
    public function itemTypes(): Collection
    {
        return ItemType::active()->with('category')->orderBy('name')->get();
    }

    #[Computed]
    public function total(): string
    {
        return Money::sum(collect($this->lines)->map(
            fn ($l) => Money::mul($l['unit_rate'] ?? 0, (int) ($l['quantity'] ?? 0))
        ));
    }

    /** The band this value will ultimately have to reach. */
    #[Computed]
    public function finalTier(): ?object
    {
        if (Money::isZero($this->total)) {
            return null;
        }

        return app(DemandService::class)->finalTierFor($this->total);
    }

    public function save(DemandService $demands): void
    {
        $this->validate([
            'department' => ['required', 'string', 'max:120'],
            'justification' => ['required', 'string', 'min:10', 'max:2000'],
            'needByDate' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_name' => ['required', 'string', 'max:180'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_rate' => ['required', 'numeric', 'min:0'],
            'lines.*.specification' => ['nullable', 'string', 'max:255'],
        ], [
            'department.required' => 'Please enter the department or class section.',
            'justification.min' => 'Say why this is needed — a sentence at least. The approvers read this.',
            'lines.*.item_name.required' => 'Name the item, or pick one from the register.',
            'lines.*.quantity.required' => 'Enter a valid quantity.',
            'lines.*.quantity.min' => 'Quantity must be at least 1.',
            'lines.*.unit_rate.required' => 'Give an estimated rate for every line.',
            'lines.*.unit_rate.min' => 'Rate cannot be negative.',
        ]);

        $demand = $demands->create(
            lines: collect($this->lines)->map(fn ($l) => [
                'item_type_id' => ! empty($l['item_type_id']) ? $l['item_type_id'] : null,
                'item_name' => trim($l['item_name']),
                'quantity' => (int) $l['quantity'],
                'unit_rate' => $l['unit_rate'],
                'specification' => ! empty($l['specification']) ? trim($l['specification']) : null,
            ])->all(),
            department: trim($this->department),
            justification: trim($this->justification),
            user: auth()->user(),
            needByDate: $this->needByDate ?: null,
        );

        session()->flash('status', "{$demand->ref} raised for ".Money::npr($demand->total_amount).
            ". It now sits with tier {$demand->current_tier}.");

        $this->redirectRoute('demands.show', $demand, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.demands.create')->title('New Demand Form');
    }
}
