<?php

namespace App\Livewire\Setup;

use App\Enums\Lifespan;
use App\Models\Category;
use App\Models\ItemType;
use App\Models\Subcategory;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The item register itself. A code prefix is unique across the whole school —
 * that uniqueness is what makes CHAIR.S.1 mean exactly one thing.
 */
class ItemTypes extends Component
{
    public string $search = '';

    public ?string $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $codePrefix = '';

    public string $categoryId = '';

    public string $subcategoryId = '';

    public string $lifespan = 'DURABLE';

    public string $unitOfMeasure = 'PCS';

    public string $indicativeRate = '';

    public string $reorderLevel = '';

    #[Computed]
    public function categories(): Collection
    {
        return Category::active()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function subcategories(): Collection
    {
        return $this->categoryId
            ? Subcategory::active()->where('category_id', $this->categoryId)->orderBy('name')->get()
            : collect();
    }

    public function newItem(): void
    {
        $this->reset(['editingId', 'name', 'codePrefix', 'subcategoryId', 'indicativeRate', 'reorderLevel']);
        $this->lifespan = 'DURABLE';
        $this->unitOfMeasure = 'PCS';
        $this->categoryId = $this->categories->first()?->id ?? '';
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function edit(string $id): void
    {
        $item = ItemType::findOrFail($id);

        $this->editingId = $id;
        $this->name = $item->name;
        $this->codePrefix = $item->code_prefix;
        $this->categoryId = $item->category_id;
        $this->subcategoryId = (string) $item->subcategory_id;
        $this->lifespan = $item->lifespan->value;
        $this->unitOfMeasure = $item->unit_of_measure;
        $this->indicativeRate = $item->indicative_rate === null ? '' : (string) $item->indicative_rate;
        $this->reorderLevel = $item->reorder_level === null ? '' : (string) $item->reorder_level;
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset([
            'editingId', 'showForm', 'name', 'codePrefix',
            'subcategoryId', 'indicativeRate', 'reorderLevel',
        ]);
    }

    public function save(AuditLogger $audit): void
    {
        $suffix = $this->editingId ? ','.$this->editingId : '';

        $this->validate([
            'name' => ['required', 'string', 'max:180'],
            'codePrefix' => ['required', 'string', 'max:40', 'unique:item_types,code_prefix'.$suffix],
            'categoryId' => ['required', 'exists:categories,id'],
            'subcategoryId' => ['nullable', 'exists:subcategories,id'],
            'lifespan' => ['required', 'string'],
            'unitOfMeasure' => ['required', 'string', 'max:16'],
            'indicativeRate' => ['nullable', 'numeric', 'min:0'],
            'reorderLevel' => ['nullable', 'integer', 'min:0'],
        ], [
            'codePrefix.unique' => 'That code prefix is already in use. Every code in the school has to be unique.',
        ]);

        $item = ItemType::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'code_prefix' => $this->codePrefix,
                'category_id' => $this->categoryId,
                'subcategory_id' => $this->subcategoryId ?: null,
                'lifespan' => Lifespan::from($this->lifespan),
                'unit_of_measure' => $this->unitOfMeasure,
                'indicative_rate' => $this->indicativeRate === '' ? null : $this->indicativeRate,
                'reorder_level' => $this->reorderLevel === '' ? null : (int) $this->reorderLevel,
            ],
        );

        $audit->record(
            action: $this->editingId ? 'ITEM_TYPE_UPDATED' : 'ITEM_TYPE_ADDED',
            entity: 'item_types',
            entityId: $item->id,
            detail: ($this->editingId ? 'Item updated: ' : 'Item added: ')
                .$item->name.' ('.$item->code_prefix.')',
        );

        session()->flash('status', $item->name.' saved.');
        $this->cancel();
    }

    public function toggle(string $id, AuditLogger $audit): void
    {
        $item = ItemType::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        $audit->record(
            action: 'ITEM_TYPE_TOGGLED',
            entity: 'item_types',
            entityId: $item->id,
            detail: $item->name.' was '.($item->is_active ? 'reactivated' : 'retired'),
        );

        session()->flash('status', $item->name.' '.($item->is_active ? 'reactivated' : 'retired').'.');
    }

    public function render(): View
    {
        $search = $this->search;

        return view('livewire.setup.item-types', [
            'items' => ItemType::with(['category', 'subcategory'])
                ->when($search, fn ($q) => $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', '%'.$search.'%')
                        ->orWhere('code_prefix', 'like', '%'.$search.'%');
                }))
                ->orderBy('name')
                ->get(),
        ])->title('Item Types');
    }
}
