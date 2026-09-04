<div>
    <x-page-header title="Stock Register"
                   subtitle="One row per item type, one column per block. Every figure is the newest entry in the ledger — never a stored total, so it cannot drift out of step with the history.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('inventory.variance') }}" wire:navigate>Variance</x-button>
            <x-button variant="secondary" href="{{ route('export.stock-register') }}">Export to Excel</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-field label="Search" for="search">
                <x-input id="search" wire:model.live.debounce.300ms="search" placeholder="Name or code, e.g. CHAIR.S" />
            </x-field>

            <x-field label="Slot" for="lifespan">
                <x-select id="lifespan" wire:model.live="lifespan">
                    <option value="">Durable and consumable</option>
                    @foreach (\App\Enums\Lifespan::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <x-field label="Category" for="categoryId">
                <x-select id="categoryId" wire:model.live="categoryId">
                    <option value="">Every category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <div class="flex items-end">
                <x-button variant="secondary" wire:click="clearFilters" class="w-full sm:w-auto">Clear filters</x-button>
            </div>
        </div>
    </x-card>

    <x-card :flush="true"
            title="{{ $rows->count() }} item type{{ $rows->count() === 1 ? '' : 's' }}"
            subtitle="{{ number_format($rows->sum('total')) }} units across {{ $blocks->count() }} blocks">
        @if ($rows->isEmpty())
            <x-empty title="Nothing matches those filters"
                     note="Try a wider search, or clear the filters to see the whole register." />
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                        <tr>
                            <th scope="col" class="sticky left-0 z-10 bg-slate-50 px-5 py-2.5 text-left font-medium dark:bg-white/5">Item</th>
                            <th scope="col" class="px-3 py-2.5 text-left font-medium">Code</th>
                            @foreach ($blocks as $block)
                                <th scope="col" class="px-3 py-2.5 text-right font-medium">{{ $block->name }}</th>
                            @endforeach
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Total</th>
                            <th scope="col" class="px-5 py-2.5 text-left font-medium">Last counted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="sticky left-0 z-10 bg-white px-5 py-2.5 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-white/5">
                                    <a href="{{ route('inventory.unit-codes', $row['item_type_id']) }}" wire:navigate
                                       class="font-medium text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-sky-400">{{ $row['item_name'] }}</a>
                                    <p class="text-xs text-slate-500 dark:text-slate-500">
                                        {{ $row['category'] }}@if ($row['subcategory']) · {{ $row['subcategory'] }}@endif
                                    </p>
                                </td>
                                <td class="px-3 py-2.5">
                                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-300">{{ $row['code_prefix'] }}</code>
                                </td>
                                @foreach ($blocks as $block)
                                    @php $qty = $row['by_block'][$block->name] ?? 0 @endphp
                                    <td class="tnum px-3 py-2.5 text-right {{ $qty ? 'text-slate-900 dark:text-slate-100' : 'text-slate-300 dark:text-slate-700' }}">{{ $qty }}</td>
                                @endforeach
                                <td class="tnum px-4 py-2.5 text-right font-semibold text-slate-900 dark:text-slate-100">{{ number_format($row['total']) }}</td>
                                <td class="px-5 py-2.5 text-xs text-slate-500 dark:text-slate-500">
                                    @if ($row['last_counted_at'])
                                        <a href="{{ route('inventory.history', $row['item_type_id']) }}" wire:navigate class="hover:text-indigo-600 dark:hover:text-sky-400">
                                            {{ \Illuminate\Support\Carbon::parse($row['last_counted_at'])->format('d M Y') }}
                                            <span class="block text-slate-400 dark:text-slate-600">{{ $row['last_counted_by'] }}</span>
                                        </a>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-600">never</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</div>
