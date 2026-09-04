<div>
    <x-page-header title="Settings">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('setup.index') }}" wire:navigate>Setup</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-errors />

    <form wire:submit="save" class="max-w-2xl space-y-6">
        <x-card title="The school">
            <x-field label="School name" for="schoolName" required
                     hint="Appears in the page header and across the top of every Excel export."
                     :error="$errors->first('schoolName')">
                <x-input id="schoolName" wire:model="schoolName" />
            </x-field>
        </x-card>

        <x-card title="Petty cash">
            <x-field label="Ceiling per bill (Rs.)" for="pettyCashCeiling" required
                     hint="Any bill above this is refused a token and redirected into the demand form and approval chain."
                     :error="$errors->first('pettyCashCeiling')">
                <x-input id="pettyCashCeiling" type="number" step="0.01" min="1"
                         wire:model="pettyCashCeiling" class="tnum text-right" />
            </x-field>

            <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:bg-white/5 dark:text-slate-400">
                Tokens already issued keep the ceiling that was in force when they were created, frozen onto the token
                itself. Changing this figure never retrospectively invalidates anything.
            </p>
        </x-card>

        <x-card title="Procurement">
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="allowOrderAboveApproval"
                       class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                <span class="text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                    Allow an order to be placed above the approved amount
                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-500">
                        It is never silent either way: the excess is written into the audit trail, and the bill will be
                        flagged as a mismatch until Accounts accepts the difference in writing.
                    </span>
                </span>
            </label>
        </x-card>

        <div class="flex justify-end">
            <x-button type="submit" busy="save">Save settings</x-button>
        </div>
    </form>
</div>
