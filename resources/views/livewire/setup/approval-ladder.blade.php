<div>
    <x-page-header title="Approval Ladder"
                   subtitle="Which rupee value needs whose signature. A form always enters at the bottom band and climbs until it reaches the band its value demands.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('setup.index') }}" wire:navigate>Setup</x-button>
        </x-slot:actions>
    </x-page-header>

    @error('ladder')
        <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-300">{{ $message }}</div>
    @enderror

    <x-errors />

    <form wire:submit="save">
        <x-card title="Bands"
                subtitle="Bands must be contiguous — each starts one rupee above the last. Only the top band may be open-ended."
                :flush="true">
            <x-slot:actions>
                <x-button variant="secondary" wire:click="addTier">Add a band</x-button>
            </x-slot:actions>

            <div class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($tiers as $i => $tier)
                    <div wire:key="tier-{{ $i }}" class="p-5">
                        <div class="grid gap-4 lg:grid-cols-12">
                            <x-field label="Tier" class="lg:col-span-1">
                                <x-input type="number" min="1" wire:model="tiers.{{ $i }}.tier_no" class="tnum text-center" />
                            </x-field>

                            <x-field label="From (Rs.)" class="lg:col-span-2" :error="$errors->first('tiers.'.$i.'.min_amount')">
                                <x-input type="number" step="0.01" min="0" wire:model="tiers.{{ $i }}.min_amount" class="tnum text-right" />
                            </x-field>

                            <x-field label="To (Rs.)" class="lg:col-span-2"
                                     hint="Blank means and above."
                                     :error="$errors->first('tiers.'.$i.'.max_amount')">
                                <x-input type="number" step="0.01" min="0" wire:model="tiers.{{ $i }}.max_amount"
                                         class="tnum text-right" placeholder="and above" />
                            </x-field>

                            <x-field label="Who decides" class="lg:col-span-4" :error="$errors->first('tiers.'.$i.'.decider_label')">
                                <x-input wire:model="tiers.{{ $i }}.decider_label" placeholder="e.g. Head of Department" />
                            </x-field>

                            <div class="flex items-end lg:col-span-2">
                                <label class="flex items-center gap-2 pb-2 text-sm text-slate-700 dark:text-slate-300">
                                    <input type="checkbox" wire:model="tiers.{{ $i }}.requires_minute"
                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                                    Minute required
                                </label>
                            </div>

                            <div class="flex items-end justify-end lg:col-span-1">
                                @if (count($tiers) > 1)
                                    <button type="button" wire:click="removeTier({{ $i }})"
                                            class="mb-0.5 rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:text-slate-600"
                                            aria-label="Remove this band">
                                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 dark:border-white/10 dark:bg-white/5">
                <p class="max-w-2xl text-xs leading-relaxed text-slate-500 dark:text-slate-500">
                    A gap between bands would mean a rupee value that nobody in the school is authorised to sign for,
                    so the save is refused if one appears. Changing the ladder does not disturb demand forms already in
                    the chain — each carries the tier it was routed to when it was raised.
                </p>
                <x-button type="submit" busy="save">Save the ladder</x-button>
            </div>
        </x-card>
    </form>
</div>
