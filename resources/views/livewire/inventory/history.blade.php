<div>
    <x-page-header :title="$itemType->name . ' — count history'"
                   subtitle="Nothing here was ever overwritten. A correction is a new row that carries the figure it replaced, so the original survives.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('inventory.unit-codes', $itemType) }}" wire:navigate>Unit codes</x-button>
            <x-button variant="secondary" href="{{ route('inventory.register') }}" wire:navigate>Back to register</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-5">
        <x-field label="Block" for="locationId" class="max-w-xs">
            <x-select id="locationId" wire:model.live="locationId">
                <option value="">Every block</option>
                @foreach ($blocks as $block)
                    <option value="{{ $block->id }}">{{ $block->name }}</option>
                @endforeach
            </x-select>
        </x-field>
    </x-card>

    <x-card :flush="true" title="{{ $entries->count() }} ledger entr{{ $entries->count() === 1 ? 'y' : 'ies' }}">
        @if ($entries->isEmpty())
            <x-empty title="No entries yet"
                     note="An entry appears the first time an auditor counts this item, or an order for it is received." />
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 font-medium">When</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Block</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Source</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Was</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Became</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Change</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">Entered by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($entries as $entry)
                            <tr class="hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="whitespace-nowrap px-5 py-2.5 text-slate-600 dark:text-slate-400">
                                    {{ $entry->counted_at->format('d M Y') }}
                                    <span class="block text-xs text-slate-400 dark:text-slate-600">{{ $entry->counted_at->format('H:i') }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ $entry->location->name }}</td>
                                <td class="px-4 py-2.5">
                                    <x-badge>{{ $entry->source->label() }}</x-badge>
                                </td>
                                <td class="tnum px-4 py-2.5 text-right text-slate-500 dark:text-slate-500">{{ $entry->previous_qty }}</td>
                                <td class="tnum px-4 py-2.5 text-right font-semibold text-slate-900 dark:text-slate-100">{{ $entry->quantity }}</td>
                                <td class="tnum px-4 py-2.5 text-right {{ $entry->delta() > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                    {{ $entry->delta() > 0 ? '+' : '' }}{{ $entry->delta() }}
                                </td>
                                <td class="px-5 py-2.5">
                                    <span class="text-slate-900 dark:text-slate-100">{{ $entry->countedBy->full_name }}</span>
                                    @if ($entry->note)
                                        <span class="block text-xs text-slate-500 dark:text-slate-500">{{ $entry->note }}</span>
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
