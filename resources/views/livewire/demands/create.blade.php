<div>
    <x-page-header title="Raise a Demand Form"
                   subtitle="The total decides which approver has to sign it. You will see that below as you type — and you can never approve your own request." />

    <x-errors />

    <form wire:submit="save" class="space-y-6">

        <x-card title="The request">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Department" for="department" required :error="$errors->first('department')">
                    <x-input id="department" wire:model="department" />
                </x-field>

                <x-field label="Needed by" for="needByDate" hint="Optional." :error="$errors->first('needByDate')">
                    <x-input id="needByDate" type="date" wire:model="needByDate" />
                </x-field>

                <x-field label="Justification" for="justification" required class="sm:col-span-2"
                         hint="The approvers read this before deciding. Say what it is for and why it is needed now."
                         :error="$errors->first('justification')">
                    <x-textarea id="justification" wire:model="justification" rows="3" />
                </x-field>
            </div>
        </x-card>

        <x-card title="Items" subtitle="Pick from the register where you can — that links the request to the stock ledger." :flush="true">
            <x-slot:actions>
                <x-button variant="secondary" wire:click="addLine">Add another item</x-button>
            </x-slot:actions>

            <div class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($lines as $i => $line)
                    <div wire:key="line-{{ $line['row_id'] ?? $i }}" class="p-5">
                        <div class="grid gap-4 lg:grid-cols-12">
                            <x-field label="On the register?" class="lg:col-span-4">
                                <x-select wire:model.live="lines.{{ $i }}.item_type_id">
                                    <option value="">Not on the register — new item</option>
                                    @foreach ($this->itemTypes as $item)
                                        <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->code_prefix }})</option>
                                    @endforeach
                                </x-select>
                            </x-field>

                            <x-field label="Item" class="lg:col-span-4" :error="$errors->first('lines.'.$i.'.item_name')">
                                <x-input wire:model.live.debounce.300ms="lines.{{ $i }}.item_name" placeholder="What is being asked for" />
                            </x-field>

                            <x-field label="Quantity" class="lg:col-span-1" :error="$errors->first('lines.'.$i.'.quantity')">
                                <x-input type="number" min="1" wire:model.live.debounce.300ms="lines.{{ $i }}.quantity" class="tnum text-right" />
                            </x-field>

                            <x-field label="Rate (Rs.)" class="lg:col-span-2" :error="$errors->first('lines.'.$i.'.unit_rate')">
                                <x-input type="number" step="0.01" min="0" wire:model.live.debounce.300ms="lines.{{ $i }}.unit_rate" class="tnum text-right" />
                            </x-field>

                            <div class="flex items-end lg:col-span-1">
                                <button type="button" wire:click="removeLine({{ $i }})"
                                        class="mb-0.5 rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:text-slate-600"
                                        title="Remove this line" aria-label="Remove this line">
                                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <x-field label="Specification" class="lg:col-span-11" hint="Optional — size, model, finish, anything the buyer needs to get it right.">
                                <x-input wire:model.live.debounce.300ms="lines.{{ $i }}.specification" />
                            </x-field>

                            <div class="flex items-end justify-end lg:col-span-1">
                                <p class="tnum pb-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                                    {{ \App\Support\Money::format(\App\Support\Money::mul($line['unit_rate'] ?? 0, (int) ($line['quantity'] ?? 0))) }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Total, and where this will have to go --}}
            <div class="border-t border-slate-200 bg-slate-50 px-5 py-4 dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-500">Total</p>
                        <x-money :amount="$this->total" class="text-2xl font-semibold text-slate-900 dark:text-slate-100" />
                    </div>

                    @if ($this->finalTier)
                        <div class="text-right">
                            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-500">This will need to reach</p>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                Tier {{ $this->finalTier->tier_no }} — {{ $this->finalTier->decider_label }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-500">
                                It starts at tier 1 and climbs, so every band below signs it too.
                                @if ($this->finalTier->requires_minute)
                                    A committee minute reference is required at the top band.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </x-card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-button variant="secondary" href="{{ route('demands.index') }}" wire:navigate>Cancel</x-button>
            <x-button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Submit for approval</span>
                <span wire:loading wire:target="save">Submitting…</span>
            </x-button>
        </div>
    </form>
</div>
