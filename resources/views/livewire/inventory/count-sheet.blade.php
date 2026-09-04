<div>
    <x-page-header title="Physical Count"
                   subtitle="Walk the block, type what is actually there, then save the whole sheet. A figure that has not moved writes nothing — only real changes reach the ledger, and the previous figure is always kept." />

    <x-errors />

    @if ($this->blocks->isEmpty())
        <x-card>
            {{-- Two different reasons produce an empty list, and telling somebody
                 to go and get themselves assigned to a block that does not exist
                 sends them to the wrong screen. --}}
            @if ($this->schoolHasNoBlocks)
                <x-empty title="No blocks have been set up yet"
                         note="A count needs somewhere to count. Add the school's blocks under Setup → Blocks, then come back." />
            @else
                <x-empty title="You are not assigned to any block"
                         note="The Super Admin sets which blocks each auditor may count in, under Setup → Staff." />
            @endif
        </x-card>
    @else
        <form wire:submit="save">
            <x-card class="mb-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-field label="Block" for="locationId" required
                             hint="Only the blocks you are assigned to.">
                        <x-select id="locationId" wire:model.live="locationId">
                            @foreach ($this->blocks as $block)
                                <option value="{{ $block->id }}">{{ $block->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Slot" for="lifespan">
                        <x-select id="lifespan" wire:model.live="lifespan">
                            @foreach (\App\Enums\Lifespan::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Search" for="search">
                        <x-input id="search" wire:model.live.debounce.300ms="search" placeholder="Name or code" />
                    </x-field>

                    <x-field label="Note for this count" for="note" hint="Optional. Attached to every line you change.">
                        <x-input id="note" wire:model="note" placeholder="e.g. Term-end count" />
                    </x-field>
                </div>
            </x-card>

            <x-card :flush="true"
                    title="{{ $this->blocks->firstWhere('id', $locationId)?->name }} — {{ $this->items->count() }} item type{{ $this->items->count() === 1 ? '' : 's' }}"
                    subtitle="Counting as {{ auth()->user()->full_name }}. Every entry is recorded against your name and cannot be edited afterwards.">
                @if ($this->items->isEmpty())
                    <x-empty title="No items match" note="Try a wider search or the other slot." />
                @else
                    <div class="table-scroll">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                                <tr>
                                    <th scope="col" class="px-5 py-2.5 font-medium">Item</th>
                                    <th scope="col" class="px-4 py-2.5 font-medium">Code</th>
                                    <th scope="col" class="px-4 py-2.5 text-right font-medium">On record</th>
                                    <th scope="col" class="px-5 py-2.5 text-right font-medium">Counted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                @foreach ($this->items as $item)
                                    @php
                                        $onRecord = $this->onRecord[$item->id] ?? 0;
                                        $typed = $counts[$item->id] ?? null;
                                        $changed = $typed !== null && $typed !== '' && (int) $typed !== $onRecord;
                                    @endphp
                                    <tr wire:key="count-{{ $item->id }}" class="{{ $changed ? 'bg-amber-50 dark:bg-amber-500/10' : '' }}">
                                        <th scope="row" class="px-5 py-2 text-left font-normal text-slate-900 dark:text-slate-100">
                                            {{ $item->name }}
                                            <span class="block text-xs text-slate-500 dark:text-slate-500">{{ $item->category->name }}</span>
                                        </th>
                                        <td class="px-4 py-2">
                                            <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-white/10 dark:text-slate-400">{{ $item->code_prefix }}</code>
                                        </td>
                                        <td class="tnum px-4 py-2 text-right text-slate-500 dark:text-slate-500">{{ $onRecord }}</td>
                                        <td class="px-5 py-2 text-right">
                                            <input type="number" min="0" inputmode="numeric"
                                                   aria-label="Counted quantity for {{ $item->name }} ({{ $item->code_prefix }}), {{ $onRecord }} on record"
                                                   wire:model.live.debounce.400ms="counts.{{ $item->id }}"
                                                   class="tnum w-24 rounded-lg border-slate-300 bg-white text-right text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500 {{ $changed ? 'border-amber-400 dark:border-amber-500/60' : '' }}" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3 dark:border-white/10 dark:bg-white/5">
                        <p class="text-xs text-slate-500 dark:text-slate-500">
                            Highlighted rows differ from the standing figure. Only those will be written.
                        </p>
                        <x-button type="submit" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">Save this count</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </x-button>
                    </div>
                @endif
            </x-card>
        </form>
    @endif
</div>
