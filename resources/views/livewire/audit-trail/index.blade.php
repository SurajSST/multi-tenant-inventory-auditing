<div>
    <x-page-header :title="$canViewAll ? 'Audit Trail' : 'My Activity Trail'"
                   :subtitle="$canViewAll
                       ? 'Every action in the system, attributed and timestamped. The database refuses UPDATE and DELETE on this table, so nothing here has ever been edited or removed.'
                       : 'Your personal activity and audit trail across the system, attributed and timestamped.'">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('export.audit-trail') }}">Export to Excel</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-5">
        <div class="grid gap-4 sm:grid-cols-2 {{ $canViewAll ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }}">
            <x-field label="Search" for="search">
                <x-input id="search" wire:model.live.debounce.300ms="search" placeholder="Reference, name, anything" />
            </x-field>

            @if ($canViewAll)
                <x-field label="Who" for="actorId">
                    <x-select id="actorId" wire:model.live="actorId">
                        <option value="">Anyone</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->id }}">{{ $actor->full_name }}</option>
                        @endforeach
                    </x-select>
                </x-field>
            @endif

            <x-field label="What" for="entity">
                <x-select id="entity" wire:model.live="entity">
                    <option value="">Everything</option>
                    @foreach ($entities as $name)
                        @if (is_string($name))
                            <option value="{{ $name }}">{{ Str::headline($name) }}</option>
                        @endif
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
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">Date & Time</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Performed By</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Action</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Module</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Activity Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5 transition-opacity duration-150" wire:loading.class="opacity-50 pointer-events-none">
                        @foreach ($entries as $entry)
                            @php
                                $actionUpper = strtoupper($entry->action);
                                $badgeClass = match (true) {
                                    str_contains($actionUpper, 'REJECT') || str_contains($actionUpper, 'FLAG') => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400',
                                    str_contains($actionUpper, 'APPROV') || str_contains($actionUpper, 'CLEAR') => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400',
                                    str_contains($actionUpper, 'ORDER') || str_contains($actionUpper, 'RECEIV') || str_contains($actionUpper, 'CREATE') => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400',
                                    default => 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-white/10 dark:text-slate-300',
                                };
                            @endphp
                            <tr wire:key="entry-{{ $entry->id }}" class="hover:bg-slate-50/75 dark:hover:bg-white/[0.02]">
                                <td class="whitespace-nowrap px-5 py-3 align-top text-xs tabular-nums">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $entry->at->format('d M Y') }}</span>
                                    <span class="block text-[11px] text-slate-400 dark:text-slate-500">{{ $entry->at->format('H:i:s') }}</span>
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <div class="text-xs font-medium text-slate-900 dark:text-slate-100">
                                        {{ $entry->actor?->full_name ?? 'System' }}
                                    </div>
                                    @if ($entry->actor?->designation)
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $entry->actor->designation }}</div>
                                    @endif
                                    @if ($entry->ip)
                                        <div class="mt-0.5 text-[10px] font-mono text-slate-400 dark:text-slate-500">{{ $entry->ip }}</div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top">
                                    <x-badge :class="$badgeClass">
                                        {{ Str::headline(Str::lower($entry->action)) }}
                                    </x-badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 align-top">
                                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-white/10 dark:text-slate-400">
                                        {{ Str::headline($entry->entity) }}
                                    </code>
                                </td>
                                <td class="px-5 py-3 align-top text-xs leading-relaxed text-slate-700 dark:text-slate-300">
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
