<?php

namespace App\Livewire\Inventory;

use App\Enums\Lifespan;
use App\Models\Category;
use App\Services\InventoryService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The register exactly as the school reads it on paper: one row per item type,
 * one column per block, plus the running total.
 */
class Register extends Component
{
    #[Url(except: '')]
    public string $lifespan = '';

    #[Url(except: '')]
    public string $categoryId = '';

    #[Url(except: '')]
    public string $search = '';

    public function clearFilters(): void
    {
        $this->reset(['lifespan', 'categoryId', 'search']);
    }

    public function render(InventoryService $inventory): View
    {
        $register = $inventory->register(
            lifespan: $this->lifespan ? Lifespan::from($this->lifespan) : null,
            categoryId: $this->categoryId ?: null,
            search: $this->search ?: null,
        );

        return view('livewire.inventory.register', [
            'blocks' => $register['blocks'],
            'rows' => $register['rows'],
            'categories' => Category::active()->orderBy('sort_order')->get(),
        ])->title('Stock Register');
    }
}
