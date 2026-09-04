<div>
    <x-page-header title="Item Types"
                   subtitle="The code prefixes themselves. Each is unique across the whole school — that is what makes CHAIR.S.1 mean exactly one chair.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('setup.index') }}" wire:navigate>Setup</x-button>
            <x-button wire:click="newItem">Add an item type</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($showForm)
        <x-card class="mb-6" :title="$editingId ? 'Edit item type' : 'New item type'">
            <form wire:submit="save">
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <x-field label="Name" for="name" required class="lg:col-span-2" :error="$errors->first('name')">
                        <x-input id="name" wire:model="name" placeholder="Chair — Senior" />
                    </x-field>

                    <x-field label="Code prefix" for="codePrefix" required
                             hint="Exactly as the school writes it." :error="$errors->first('codePrefix')">
                        <x-input id="codePrefix" wire:model="codePrefix" placeholder="CHAIR.S" />
                    </x-field>

                    <x-field label="Slot" for="lifespan" required
                             hint="A year or more is durable." :error="$errors->first('lifespan')">
                        <x-select id="lifespan" wire:model="lifespan">
                            @foreach (\App\Enums\Lifespan::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Category" for="categoryId" required :error="$errors->first('categoryId')">
                        <x-select id="categoryId" wire:model.live="categoryId">
                            @foreach ($this->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Subcategory" for="subcategoryId" :error="$errors->first('subcategoryId')">
                        <x-select id="subcategoryId" wire:model="subcategoryId">
                            <option value="">None</option>
                            @foreach ($this->subcategories as $sub)
                                <option value="{{ $sub->id }}">{{ $sub->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Unit" for="unitOfMeasure" required :error="$errors->first('unitOfMeasure')">
                        <x-input id="unitOfMeasure" wire:model="unitOfMeasure" placeholder="PCS" />
                    </x-field>

                    <x-field label="Indicative rate (Rs.)" for="indicativeRate"
                             hint="Pre-fills demand forms." :error="$errors->first('indicativeRate')">
                        <x-input id="indicativeRate" type="number" step="0.01" min="0"
                                 wire:model="indicativeRate" class="tnum text-right" />
                    </x-field>

                    <x-field label="Reorder level" for="reorderLevel"
                             hint="Alerts when the total across all blocks falls to this."
                             :error="$errors->first('reorderLevel')">
                        <x-input id="reorderLevel" type="number" min="0" wire:model="reorderLevel" class="tnum text-right" />
                    </x-field>
                </div>

                <div class="mt-5 flex gap-2">
                    <x-button type="submit" busy="save">{{ $editingId ? 'Save changes' : 'Add item type' }}</x-button>
                    <x-button variant="secondary" wire:click="cancel">Cancel</x-button>
                </div>
            </form>
        </x-card>
    @endif

    <x-card class="mb-5">
        <x-field label="Search" for="search" class="max-w-sm">
            <x-input id="search" wire:model.live.debounce.300ms="search" placeholder="Name or code prefix" />
        </x-field>
    </x-card>

    <x-card :flush="true" title="{{ $items->count() }} item type{{ $items->count() === 1 ? '' : 's' }}">
        <div class="table-scroll">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-2.5 font-medium">Item</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Code</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Category</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Slot</th>
                        <th scope="col" class="px-4 py-2.5 text-right font-medium">Rate</th>
                        <th scope="col" class="px-4 py-2.5 text-right font-medium">Reorder at</th>
                        <th scope="col" class="sticky right-0 z-20 border-l border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-[#111C2E] px-5 py-2.5 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @foreach ($items as $item)
                        <tr wire:key="item-{{ $item->id }}" class="group {{ $item->is_active ? '' : 'opacity-50' }} hover:bg-slate-50 dark:hover:bg-white/5">
                            <td class="px-5 py-2.5">
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ $item->name }}</span>
                                @unless ($item->is_active)
                                    <x-badge class="ml-1.5">retired</x-badge>
                                @endunless
                            </td>
                            <td class="px-4 py-2.5">
                                <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-300">{{ $item->code_prefix }}</code>
                            </td>
                            <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400">
                                {{ $item->category->name }}
                                @if ($item->subcategory)
                                    <span class="block text-xs text-slate-400 dark:text-slate-600">{{ $item->subcategory->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                <x-badge class="{{ $item->lifespan === \App\Enums\Lifespan::DURABLE
                                    ? 'bg-sky-50 text-sky-800 ring-sky-600/20'
                                    : 'bg-amber-50 text-amber-800 ring-amber-600/20' }}">
                                    {{ $item->lifespan->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @if ($item->indicative_rate)
                                    <x-money :amount="$item->indicative_rate" :bare="true" class="text-slate-600 dark:text-slate-400" />
                                @else
                                    <span class="text-slate-300 dark:text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="tnum px-4 py-2.5 text-right text-slate-600 dark:text-slate-400">{{ $item->reorder_level ?? '—' }}</td>
                            <td class="sticky right-0 z-10 border-l border-slate-200 bg-white group-hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:group-hover:bg-[#111C2E] px-5 py-2.5 text-right">
                                <button type="button" wire:click="edit('{{ $item->id }}')"
                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-400">Edit</button>
                                <button type="button" wire:click="toggle('{{ $item->id }}')"
                                        class="ml-3 text-xs font-medium text-slate-500 hover:text-slate-900 dark:text-slate-500 dark:hover:text-slate-100">
                                    {{ $item->is_active ? 'Retire' : 'Reactivate' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</div>
