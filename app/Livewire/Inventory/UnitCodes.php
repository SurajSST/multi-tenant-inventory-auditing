<?php

namespace App\Livewire\Inventory;

use App\Models\ItemType;
use App\Services\InventoryService;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Every physical unit expanded to its own code — CHAIR.S.1, CHAIR.S.2, … —
 * numbered across blocks in block order, the way the original sheet is written.
 */
class UnitCodes extends Component
{
    public ItemType $itemType;

    public function mount(ItemType $itemType): void
    {
        $this->itemType = $itemType;
    }

    public function render(InventoryService $inventory): View
    {
        $expanded = $inventory->unitCodes($this->itemType->id);

        return view('livewire.inventory.unit-codes', [
            'units' => $expanded['units'],
            'total' => $expanded['total'],
        ])->title($this->itemType->code_prefix.' — unit codes');
    }
}
