<div>
    <x-page-header title="Blocks and Locations"
                   subtitle="Where stock physically sits. New blocks can be added at any time, each with its own code prefix.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('setup.index') }}" wire:navigate>Setup</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-1" :title="$editingId ? 'Edit block' : 'Add a block'">
            <form wire:submit="save" class="space-y-5">
                <x-field label="Name" for="name" required :error="$errors->first('name')">
                    <x-input id="name" wire:model="name" placeholder="Block G" />
                </x-field>

                <x-field label="Code" for="code" required
                         hint="Short prefix used where a block needs its own coding."
                         :error="$errors->first('code')">
                    <x-input id="code" wire:model="code" placeholder="G" class="uppercase" />
                </x-field>

                <x-field label="Note" for="note" hint="Optional." :error="$errors->first('note')">
                    <x-input id="note" wire:model="note" />
                </x-field>

                <div class="flex gap-2">
                    <x-button type="submit" busy="save">{{ $editingId ? 'Save changes' : 'Add block' }}</x-button>
                    @if ($editingId)
                        <x-button variant="secondary" wire:click="cancel">Cancel</x-button>
                    @endif
                </div>
            </form>
        </x-card>

        <x-card class="lg:col-span-2" :flush="true" title="{{ $locations->count() }} block(s)">
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 font-medium">Block</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Code</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Ledger entries</th>
                            <th scope="col" class="sticky right-0 z-20 border-l border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-[#111C2E] px-5 py-2.5 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($locations as $location)
                            <tr wire:key="block-{{ $location->id }}" class="group {{ $location->is_active ? '' : 'opacity-50' }} hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-5 py-2.5">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $location->name }}</span>
                                    @unless ($location->is_active)
                                        <x-badge class="ml-1.5">retired</x-badge>
                                    @endunless
                                    @if ($location->note)
                                        <span class="block text-xs text-slate-500 dark:text-slate-500">{{ $location->note }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700 dark:bg-white/10 dark:text-slate-300">{{ $location->code }}</code>
                                </td>
                                <td class="tnum px-4 py-2.5 text-right text-slate-600 dark:text-slate-400">{{ $location->counts_count }}</td>
                                <td class="sticky right-0 z-10 border-l border-slate-200 bg-white group-hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:group-hover:bg-[#111C2E] px-5 py-2.5 text-right">
                                    <button type="button" wire:click="edit('{{ $location->id }}')"
                                            class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-400">Edit</button>
                                    <button type="button" wire:click="toggle('{{ $location->id }}')"
                                            class="ml-3 text-xs font-medium text-slate-500 hover:text-slate-900 dark:text-slate-500 dark:hover:text-slate-100">
                                        {{ $location->is_active ? 'Retire' : 'Reactivate' }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs leading-relaxed text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-500">
                A block is retired rather than deleted — its ledger entries stay readable forever, and retiring it only
                removes it from new counts and the register.
            </p>
        </x-card>
    </div>
</div>
