<?php

namespace App\Livewire\Setup;

use App\Models\Category;
use App\Models\Subcategory;
use App\Services\AuditLogger;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Categories extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public string $name = '';

    public string $code = '';

    public ?string $editingId = null;

    /** category id => the subcategory name being typed against it */
    public array $newSub = [];

    public function edit(string $id): void
    {
        $category = Category::findOrFail($id);

        $this->editingId = $id;
        $this->name = $category->name;
        $this->code = $category->code;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'code']);
    }

    public function save(AuditLogger $audit): void
    {
        $suffix = $this->editingId ? ','.$this->editingId : '';

        $this->validate([
            'name' => ['required', 'string', 'max:120', 'unique:categories,name'.$suffix],
            'code' => ['required', 'string', 'max:12', 'unique:categories,code'.$suffix],
        ]);

        $category = Category::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'code' => strtoupper($this->code),
                'sort_order' => $this->editingId
                    ? Category::find($this->editingId)->sort_order
                    : Category::max('sort_order') + 1,
            ],
        );

        $audit->record(
            action: $this->editingId ? 'CATEGORY_UPDATED' : 'CATEGORY_ADDED',
            entity: 'categories',
            entityId: $category->id,
            detail: ($this->editingId ? 'Category updated: ' : 'Category added: ')
                .$category->name.' ('.$category->code.')',
        );

        $this->dispatch('toast', message: $category->name.' saved.', tone: 'success', title: 'Category saved');
        $this->cancel();
    }

    public function addSubcategory(string $categoryId, AuditLogger $audit): void
    {
        $name = trim($this->newSub[$categoryId] ?? '');

        if ($name === '') {
            return;
        }

        $category = Category::findOrFail($categoryId);

        $sub = Subcategory::firstOrCreate(
            ['category_id' => $category->id, 'name' => $name],
            ['is_active' => true],
        );

        $audit->record(
            action: 'SUBCATEGORY_ADDED',
            entity: 'subcategories',
            entityId: $sub->id,
            detail: 'Subcategory added: '.$sub->name.' under '.$category->name,
        );

        $this->newSub[$categoryId] = '';
        $this->dispatch('toast', message: $name.' added.', tone: 'success', title: 'Subcategory added');
    }

    /**
     * Retiring a category hides it from new demand forms and the register.
     * Historical records that point at it are untouched — nothing in this
     * system is deleted once it has been used.
     */
    public function toggle(string $id, AuditLogger $audit): void
    {
        $category = Category::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);

        $audit->record(
            action: 'CATEGORY_TOGGLED',
            entity: 'categories',
            entityId: $category->id,
            detail: $category->name.' was '.($category->is_active ? 'reactivated' : 'retired'),
        );

        $this->dispatch('toast', message: $category->name.' '.($category->is_active ? 'reactivated' : 'retired').'.', tone: $category->is_active ? 'success' : 'warning', title: $category->is_active ? 'Category restored' : 'Category retired');
    }

    public function render(): View
    {
        return view('livewire.setup.categories', [
            'categories' => Category::with('subcategories')
                ->withCount('itemTypes')
                ->when($this->search, fn ($query) => $query->where(fn ($q) => $q
                    ->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('code', 'like', '%'.$this->search.'%')))
                ->orderBy('sort_order')
                ->paginate(20),
        ])->title('Categories');
    }
}
