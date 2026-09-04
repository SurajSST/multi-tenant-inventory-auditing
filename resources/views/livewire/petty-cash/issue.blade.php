<div>
    <x-page-header title="Issue a Petty Cash Token"
                   subtitle="Create this with the claimant and the original bill in front of you. Your name goes on the token, and a second person in Accounts has to release the money." />

    <x-errors />

    <form wire:submit="save" class="space-y-6">

        <x-card title="The bill">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Bill number" for="billNo" required
                         hint="Must not already exist as a token or in the main bill register."
                         :error="$errors->first('billNo')">
                    <x-input id="billNo" wire:model="billNo" />
                </x-field>

                <x-field label="Bill date" for="billDate" hint="Optional." :error="$errors->first('billDate')">
                    <x-input id="billDate" type="date" wire:model="billDate" />
                </x-field>

                <x-field label="Vendor / shop" for="vendorName" required :error="$errors->first('vendorName')">
                    <x-input id="vendorName" wire:model="vendorName" />
                </x-field>

                <x-field label="Amount (Rs.)" for="amount" required
                         :hint="'Ceiling is ' . \App\Support\Money::npr($this->ceiling) . ' per bill.'"
                         :error="$errors->first('amount')">
                    <x-input id="amount" type="number" step="0.01" min="0.01"
                             wire:model.live.debounce.400ms="amount" class="tnum text-right" />
                </x-field>
            </div>

            @if ($this->overCeiling)
                <p class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-relaxed text-rose-900 dark:bg-rose-500/10">
                    <strong>{{ \App\Support\Money::npr($amount) }}</strong> is above the petty cash ceiling of
                    {{ \App\Support\Money::npr($this->ceiling) }}. This cannot be issued as a token — it has to go
                    through a demand form and the approval chain instead.
                    <a href="{{ route('demands.create') }}" wire:navigate class="font-semibold underline">Raise a demand form</a>.
                </p>
            @endif
        </x-card>

        <x-card title="Who is claiming it">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Claimant's name" for="claimantName" required
                         hint="The person physically standing in front of you."
                         :error="$errors->first('claimantName')">
                    <x-input id="claimantName" wire:model="claimantName" />
                </x-field>

                <x-field label="Purpose" for="purpose" required
                         hint="What the money was spent on." :error="$errors->first('purpose')">
                    <x-input id="purpose" wire:model="purpose" />
                </x-field>
            </div>

            <label class="mt-5 flex items-start gap-3 rounded-lg border border-slate-200 p-4 dark:border-white/10">
                <input type="checkbox" wire:model="billSighted"
                       class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                <span class="text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                    I have the <strong>original bill</strong> in front of me and have checked it against the
                    figures above.
                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-500">
                        The database refuses any token where this is not ticked.
                    </span>
                </span>
            </label>
            @error('billSighted') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

            <p class="mt-5 rounded-lg bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:bg-white/5 dark:text-slate-400">
                This token will be issued in the name of <strong>{{ auth()->user()->full_name }}</strong>.
                You will not be able to release the payment yourself — that has to be somebody else in Accounts.
            </p>
        </x-card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-button variant="secondary" href="{{ route('petty-cash.index') }}" wire:navigate>Cancel</x-button>
            <x-button type="submit" :disabled="$this->overCeiling" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Generate the token</span>
                <span wire:loading wire:target="save">Generating…</span>
            </x-button>
        </div>
    </form>
</div>
