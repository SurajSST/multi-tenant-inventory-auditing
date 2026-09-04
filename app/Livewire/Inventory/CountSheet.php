<?php

namespace App\Livewire\Inventory;

use App\Enums\Lifespan;
use App\Models\ItemType;
use App\Models\Location;
use App\Services\InventoryService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The auditor's count sheet: pick a block, walk it, type what is actually
 * there, save the whole sheet at once.
 *
 * Lines whose figure has not moved write nothing, so re-counting a block that
 * has not changed adds no noise to the ledger.
 */
class CountSheet extends Component
{
    public string $locationId = '';

    public string $lifespan = Lifespan::DURABLE->value;

    public string $search = '';

    public string $note = '';

    /** item type id => counted quantity, as typed */
    public array $counts = [];

    public function mount(): void
    {
        $this->locationId = $this->blocks->first()?->id ?? '';
        $this->loadCurrent();
    }

    /** Blocks this auditor is allowed to count in. */
    /**
     * No blocks at all, as opposed to none assigned to this auditor.
     *
     * The two look identical on screen and need opposite advice: one sends the
     * Super Admin to set the school up, the other sends an auditor to ask for
     * a block. A new school starts with neither.
     */
    #[Computed]
    public function schoolHasNoBlocks(): bool
    {
        return Location::active()->doesntExist();
    }

    #[Computed]
    public function blocks(): Collection
    {
        $scope = auth()->user()->scopedLocationIds();

        return Location::active()
            ->when($scope, fn ($q) => $q->whereIn('id', $scope))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function items(): Collection
    {
        return ItemType::active()
            ->where('lifespan', $this->lifespan)
            ->when($this->search, fn ($q) => $q->where(function ($w) {
                $w->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code_prefix', 'like', "%{$this->search}%");
            }))
            ->with('category')
            ->orderBy('name')
            ->get();
    }

    /** What the ledger says right now, for the block on screen. */
    #[Computed]
    public function onRecord(): array
    {
        return $this->locationId
            ? app(InventoryService::class)->currentByLocation($this->locationId)
            : [];
    }

    public function updatedLocationId(): void
    {
        $this->loadCurrent();
    }

    public function updatedLifespan(): void
    {
        $this->loadCurrent();
    }

    /** Pre-fill the sheet with the standing figures so only changes get typed. */
    private function loadCurrent(): void
    {
        unset($this->onRecord, $this->items);

        $current = $this->onRecord;

        $this->counts = $this->items
            ->mapWithKeys(fn (ItemType $item) => [$item->id => (string) ($current[$item->id] ?? 0)])
            ->all();
    }

    public function save(InventoryService $inventory): void
    {
        $this->validate([
            'locationId' => ['required', 'string', 'exists:locations,id'],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['counts.*' => 'count']);

        $lines = collect($this->counts)
            ->filter(fn ($q) => $q !== '' && $q !== null)
            ->map(fn ($q, $itemTypeId) => [
                'item_type_id' => $itemTypeId,
                'location_id' => $this->locationId,
                'quantity' => (int) $q,
            ])
            ->values()
            ->all();

        $written = $inventory->submitCount($lines, auth()->user(), note: $this->note ?: null);

        $block = $this->blocks->firstWhere('id', $this->locationId)?->name;

        session()->flash('status', $written->isEmpty()
            ? "Nothing had changed in {$block}, so no ledger entries were written."
            : "{$written->count()} change(s) recorded for {$block}. The previous figures are kept in the history.");

        $this->note = '';
        $this->loadCurrent();
    }

    public function render(): View
    {
        return view('livewire.inventory.count-sheet')->title('Physical Count');
    }
}
