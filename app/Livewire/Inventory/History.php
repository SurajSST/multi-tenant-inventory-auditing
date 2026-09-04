<?php

namespace App\Livewire\Inventory;

use App\Models\ItemType;
use App\Models\Location;
use App\Services\InventoryService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The ledger for one item type. Nothing here was ever overwritten — a
 * correction is a new row that carries the figure it replaced.
 */
class History extends Component
{
    public ItemType $itemType;

    #[Url(except: '')]
    public string $locationId = '';

    public function mount(ItemType $itemType): void
    {
        $this->itemType = $itemType;
    }

    public function render(InventoryService $inventory): View
    {
        return view('livewire.inventory.history', [
            'entries' => $inventory->history($this->itemType->id, $this->locationId ?: null),
            'blocks' => Location::active()->orderBy('name')->get(),
        ])->title($this->itemType->name.' — count history');
    }
}
