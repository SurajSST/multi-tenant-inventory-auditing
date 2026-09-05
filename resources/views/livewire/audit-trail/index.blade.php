<div>
    <x-page-header title="Audit Trail"
                   subtitle="Every action in the system, attributed and timestamped. The database refuses UPDATE and DELETE on this table, so nothing here has ever been edited or removed.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('export.audit-trail') }}">Export to Excel</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-field label="Search" for="search">
                <x-input id="search" wire:model.live.debounce.300ms="search" placeholder="Reference, name, anything" />
            </x-field>

            <x-field label="Who" for="actorId">
                <x-select id="actorId" wire:model.live="actorId">
                    <option value="">Anyone</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}">{{ $actor->full_name }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <x-field label="What" for="entity">
                <x-select id="entity" wire:model.live="entity">
                    <option value="">Everything</option>
                    @foreach ($entities as $name)
                        <option value="{{ $name }}">{{ Str::headline($name) }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <x-field label="From" for="from">
                <x-input id="from" type="date" wire:model.live="from" />
            </x-field>

            <x-field label="To" for="to">
                <x-input id="to" type="date" wire:model.live="to" />
            </x-field>
        </div>

        <div class="mt-4">
            <x-button variant="secondary" wire:click="clearFilters">Clear filters</x-button>
        </div>
    </x-card>

    <x-card :flush="true" title="{{ number_format($entries->total()) }} entr{{ $entries->total() === 1 ? 'y' : 'ies' }}">
        @if ($entries->isEmpty())
            <x-empty title="Nothing matches those filters" note="Try widening the date range or clearing the filters." />
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:bg-white/5 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-3">When</th>
                            <th scope="col" class="px-5 py-3">Actor</th>
                            <th scope="col" class="px-5 py-3">Action</th>
                            <th scope="col" class="px-5 py-3">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($entries as $entry)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-white/[0.02]">
                                <td class="whitespace-nowrap px-5 py-3.5 align-top text-xs text-slate-500 dark:text-slate-400">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $entry->at->format('d M Y') }}</div>
                                    <div class="tabular-nums text-slate-400 dark:text-slate-500">{{ $entry->at->format('H:i:s') }}</div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 align-top">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $entry->actor?->full_name ?? 'System' }}</div>
                                    @if ($entry->actor)
                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                            {{ $entry->actor->designation }}
                                            @if ($entry->ip) · <span class="text-slate-400 dark:text-slate-500">{{ $entry->ip }}</span> @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 align-top">
                                    <x-badge>{{ Str::headline(Str::lower($entry->action)) }}</x-badge>
                                </td>
                                <td class="px-5 py-3.5 align-top text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                    {{ $entry->detail }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-5 py-3 dark:border-white/10">
                {{ $entries->links() }}
            </div>
        @endif
    </x-card>
</div>
