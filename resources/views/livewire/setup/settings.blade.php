<div class="mx-auto max-w-6xl space-y-6">
    <x-page-header title="School settings"
                   subtitle="Set the school identity and policy defaults used by procurement and petty cash.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('setup.index') }}" wire:navigate>Settings & Roles</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-errors />
    <form wire:submit="save" class="space-y-5">
        <div class="grid gap-5 lg:grid-cols-2">
            <x-card title="School identity" subtitle="Used in the application header and exported reports.">
                <x-field label="School name" for="schoolName" required
                         hint="This name appears throughout the system and on Excel exports."
                         :error="$errors->first('schoolName')">
                    <x-input id="schoolName" wire:model="schoolName" placeholder="School name" />
                </x-field>
            </x-card>

            <x-card title="Petty cash" subtitle="Set the maximum amount allowed on a single petty cash token.">
                <x-field label="Ceiling per bill (Rs.)" for="pettyCashCeiling" required
                         :error="$errors->first('pettyCashCeiling')">
                    <x-input id="pettyCashCeiling" type="number" step="0.01" min="1"
                             wire:model="pettyCashCeiling" class="tnum text-right" />
                </x-field>
                <p class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:border-white/10 dark:bg-white/[.03] dark:text-slate-400">
                    Tokens already issued keep the ceiling that was in force when they were created. Changing this value does not affect existing tokens.
                </p>
            </x-card>
        </div>

        <x-card title="Procurement policy" subtitle="Orders above the approved amount are always recorded and flagged for Accounts. This controls whether the purchase officer may place one.">
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 p-4 transition hover:border-indigo-300 dark:border-white/10 dark:hover:border-sky-500/40">
                <input type="checkbox" wire:model="allowOrderAboveApproval"
                       class="mt-0.5 size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                <span>
                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Allow orders above the approved amount</span>
                </span>
            </label>
        </x-card>

        <x-card title="Demand Approval Policy" subtitle="Configure how requisition forms route through approval tiers.">
            <div class="space-y-3">
                @foreach ($approvalModes as $mode)
                    <label wire:key="approval-mode-{{ $mode->value }}"
                           class="flex cursor-pointer items-start gap-3.5 rounded-xl border p-4 transition-all {{ $demandApprovalMode === $mode->value ? 'border-indigo-600 bg-indigo-50/40 ring-1 ring-indigo-600 dark:border-sky-500 dark:bg-sky-500/10 dark:ring-sky-500' : 'border-slate-200 hover:border-slate-300 dark:border-white/10 dark:hover:border-white/20' }}">
                        <input type="radio" value="{{ $mode->value }}" wire:model.live="demandApprovalMode"
                               class="mt-1 size-4 border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                        <div class="flex-1">
                            <span class="block text-sm font-semibold text-slate-900 dark:text-slate-100">
                                {{ $mode->label() }}
                            </span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ $mode->description() }}
                            </span>
                        </div>
                    </label>
                @endforeach
            </div>
            @error('demandApprovalMode')
                <p class="mt-2 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </x-card>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm dark:border-white/10 dark:bg-slate-900">
            <p class="text-xs text-slate-500 dark:text-slate-400">Changes are recorded in the school audit trail.</p>
            <x-button type="submit" busy="save">Save settings</x-button>
        </div>
    </form>
</div>
