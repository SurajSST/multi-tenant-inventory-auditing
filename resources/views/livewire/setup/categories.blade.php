<div>
    <x-page-header title="Categories"
                   subtitle="How the register groups things. Each category holds any number of subcategories, and both can be added at will.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('setup.index') }}" wire:navigate>Setup</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-1" :title="$editingId ? 'Edit category' : 'Add a category'">
            <form wire:submit="save" class="space-y-5">
                <x-field label="Name" for="name" required :error="$errors->first('name')">
                    <x-input id="name" wire:model="name" placeholder="Laboratory Equipment" />
                </x-field>

                <x-field label="Code" for="code" required :error="$errors->first('code')">
                    <x-input id="code" wire:model="code" placeholder="LAB" class="uppercase" />
                </x-field>

                <div class="flex gap-2">
                    <x-button type="submit" busy="save">{{ $editingId ? 'Save changes' : 'Add category' }}</x-button>
                    @if ($editingId)
                        <x-button variant="secondary" wire:click="cancel">Cancel</x-button>
                    @endif
                </div>
            </form>
        </x-card>

        <div class="space-y-4 lg:col-span-2">
            @foreach ($categories as $category)
                <x-card wire:key="cat-{{ $category->id }}" class="{{ $category->is_active ? '' : 'opacity-60' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $category->name }}</h3>
                                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-300">{{ $category->code }}</code>
                                @unless ($category->is_active)
                                    <x-badge>retired</x-badge>
                                @endunless
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-500">
                                {{ $category->item_types_count }} item type{{ $category->item_types_count === 1 ? '' : 's' }}
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" wire:click="edit('{{ $category->id }}')"
                                    class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-400">Edit</button>
                            <button type="button" wire:click="toggle('{{ $category->id }}')"
                                    class="text-xs font-medium text-slate-500 hover:text-slate-900 dark:text-slate-500 dark:hover:text-slate-100">
                                {{ $category->is_active ? 'Retire' : 'Reactivate' }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @forelse ($category->subcategories as $sub)
                            <x-badge>{{ $sub->name }}</x-badge>
                        @empty
                            <span class="text-xs text-slate-400 dark:text-slate-600">No subcategories yet.</span>
                        @endforelse
                    </div>

                    <form wire:submit="addSubcategory('{{ $category->id }}')" class="mt-3 flex gap-2">
                        <x-input wire:model="newSub.{{ $category->id }}" placeholder="Add a subcategory" class="flex-1" />
                        <x-button variant="secondary" type="submit">Add</x-button>
                    </form>
                </x-card>
            @endforeach
        </div>
    </div>
</div>
