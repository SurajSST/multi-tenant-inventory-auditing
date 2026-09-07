<?php

namespace App\Livewire\Inventory;

use App\Models\Location;
use App\Services\InventoryService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What the school bought through this system against what is physically there.
 * A large gap is the first thing worth asking about.
 */
class Variance extends Component
{
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $locationId = '';

    #[Url(except: false)]
    public bool $onlyDiscrepancies = false;

    public function render(InventoryService $inventory): View
    {
        $rows = $inventory->variance($this->locationId ?: null);

        if ($this->onlyDiscrepancies) {
            $rows = $rows->filter(fn ($r) => (int) $r->variance !== 0)->values();
        }

        if ($this->search) {
            $needle = strtolower(trim($this->search));
            $rows = $rows->filter(function ($r) use ($needle) {
                return str_contains(strtolower($r->item_name ?? ''), $needle)
                    || str_contains(strtolower($r->category_name ?? ''), $needle)
                    || str_contains(strtolower($r->location_name ?? ''), $needle);
            })->values();
        }

        return view('livewire.inventory.variance', [
            'rows' => $rows,
            'blocks' => Location::active()->orderBy('name')->get(),
        ])->title('Stock Variance');
    }
}
