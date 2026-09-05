<div>
    <x-page-header title="Demand Forms"
                   :subtitle="$seesEverything
                        ? 'Every request in the school, with where it currently sits.'
                        : 'The requests you have raised, and where each one currently sits.'">
        <x-slot:actions>
            @can('raise-demands')
                <x-button href="{{ route('demands.create') }}" wire:navigate>Raise a demand</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-5">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field label="Status" for="status">
                <x-select id="status" wire:model.live="status">
                    <option value="">Every status</option>
                    @foreach (\App\Enums\DemandStatus::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <x-field label="Department" for="department">
                <x-select id="department" wire:model.live="department">
                    <option value="">All departments</option>
                    @foreach ($this->departments as $dept)
                        <option value="{{ $dept }}">{{ $dept }}</option>
                    @endforeach
                </x-select>
            </x-field>

            @if ($seesEverything)
                <div class="flex items-end">
                    <label class="flex items-center gap-2 pb-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input type="checkbox" wire:model.live="mine"
                               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                        Only the ones I raised
                    </label>
                </div>
            @endif
        </div>
    </x-card>

    <x-card :flush="true" title="{{ $demands->total() }} form{{ $demands->total() === 1 ? '' : 's' }}">
        @if ($demands->isEmpty())
            <x-empty title="No demand forms yet"
                     note="A demand form is how anything gets bought. Raise one and it routes itself to the right approver.">
                @can('raise-demands')
                    <x-button href="{{ route('demands.create') }}" wire:navigate>Raise a demand</x-button>
                @endcan
            </x-empty>
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Demand Ref</th>
                            <th scope="col" class="px-4 py-3 font-medium">Department</th>
                            <th scope="col" class="px-4 py-3 font-medium">Raised By</th>
                            <th scope="col" class="px-3 py-3 text-center font-medium">Items</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium">Est. Amount</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status & Flow</th>
                            <th scope="col" class="px-4 py-3 font-medium">Last Activity</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($demands as $demand)
                            <tr wire:key="demand-{{ $demand->id }}" class="hover:bg-slate-50 dark:hover:bg-white/[0.03] transition-colors">
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <a href="{{ route('demands.show', $demand) }}" wire:navigate
                                       class="font-semibold text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-sky-400">
                                        {{ $demand->ref }}
                                    </a>
                                    <span class="block text-xs text-slate-400 dark:text-slate-500">
                                        {{ $demand->created_at->format('d M Y, H:i') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-slate-700 dark:text-slate-300">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300">
                                        {{ $demand->department }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $demand->raisedBy->full_name }}</span>
                                    @if ($demand->raisedBy->designation)
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $demand->raisedBy->designation }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5 text-center whitespace-nowrap">
                                    <span class="tnum font-medium text-slate-700 dark:text-slate-300">
                                        {{ $demand->lines_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap font-semibold">
                                    <x-money :amount="$demand->total_amount" class="text-slate-900 dark:text-slate-100" />
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <x-badge :class="$demand->status->badge()">{{ $demand->status->label() }}</x-badge>
                                        @if ($demand->isPending())
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">
                                                Tier {{ $demand->current_tier }} of {{ $demand->final_tier }}
                                            </span>
                                        @endif
                                        @if ($demand->orders->isNotEmpty())
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-sky-50 text-sky-800 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300">
                                                {{ $demand->orders->first()->ref }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                    @if ($demand->approvals->isNotEmpty())
                                        <span>Decision by <strong class="font-medium text-slate-700 dark:text-slate-300">{{ $demand->approvals->last()->actor->full_name }}</strong></span>
                                        <span class="block text-[11px] text-slate-400 dark:text-slate-500">{{ $demand->approvals->last()->decided_at?->diffForHumans() }}</span>
                                    @else
                                        <span class="text-slate-400 dark:text-slate-600">Pending initial decision</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <x-button variant="secondary" size="xs" href="{{ route('demands.show', $demand) }}" wire:navigate>
                                        View
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($demands->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-white/5">{{ $demands->links() }}</div>
        @endif
    </x-card>
</div>
