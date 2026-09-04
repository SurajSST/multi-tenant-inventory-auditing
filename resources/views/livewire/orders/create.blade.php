<div>
    <x-page-header title="Place an Order"
                   subtitle="Only against a fully approved demand form, and one order per demand. This order is locked to your name — somebody else has to verify the goods when they arrive." />

    <x-errors />

    @if ($this->awaiting->isEmpty())
        <x-card>
            <x-empty title="Nothing is approved and waiting"
                     note="An order can only be placed against a demand form that has cleared every tier it needed to reach.">
                <x-button variant="secondary" href="{{ route('demands.index') }}" wire:navigate>See demand forms</x-button>
            </x-empty>
        </x-card>
    @else
        <form wire:submit="save" class="space-y-6">

            <x-card title="Which approved demand?">
                <x-field label="Demand form" for="demandId" required :error="$errors->first('demandId')">
                    <x-select id="demandId" wire:model.live="demandId">
                        <option value="">Choose one</option>
                        @foreach ($this->awaiting as $demand)
                            <option value="{{ $demand->id }}">
                                {{ $demand->ref }} — {{ $demand->department }} — {{ \App\Support\Money::npr($demand->total_amount) }}
                            </option>
                        @endforeach
                    </x-select>
                </x-field>

                @if ($this->demand)
                    <div class="mt-5 rounded-lg border border-slate-200 dark:border-white/10">
                        <div class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-white/10 dark:bg-white/5">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-500">What was approved</p>
                        </div>
                        <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/5">
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                @foreach ($this->demand->lines as $line)
                                    <tr>
                                        <td class="px-4 py-2 text-slate-900 dark:text-slate-100">
                                            {{ $line->item_name }}
                                            @if ($line->specification)
                                                <span class="block text-xs text-slate-500 dark:text-slate-500">{{ $line->specification }}</span>
                                            @endif
                                        </td>
                                        <td class="tnum px-4 py-2 text-right text-slate-600 dark:text-slate-400">{{ $line->quantity }} ×</td>
                                        <td class="px-4 py-2 text-right"><x-money :amount="$line->unit_rate" :bare="true" class="text-slate-600 dark:text-slate-400" /></td>
                                        <td class="px-4 py-2 text-right"><x-money :amount="$line->line_total" :bare="true" class="font-medium text-slate-900 dark:text-slate-100" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-slate-50 dark:bg-white/5">
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-500">Approved total</td>
                                    <td class="px-4 py-2 text-right"><x-money :amount="$this->demand->total_amount" :bare="true" class="text-sm font-semibold text-slate-900 dark:text-slate-100" /></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </x-card>

            @if ($this->demand)
                <x-card title="The vendor">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field label="Existing vendor" for="vendorId" hint="Leave blank to add a new one below.">
                            <x-select id="vendorId" wire:model.live="vendorId">
                                <option value="">New vendor</option>
                                @foreach ($this->vendors as $vendor)
                                    <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                @endforeach
                            </x-select>
                        </x-field>

                        @if (! $vendorId)
                            <x-field label="Vendor name" for="vendorName" required :error="$errors->first('vendorName')">
                                <x-input id="vendorName" wire:model="vendorName" />
                            </x-field>

                            <x-field label="PAN / VAT" for="vendorPanVat" hint="Optional." :error="$errors->first('vendorPanVat')">
                                <x-input id="vendorPanVat" wire:model="vendorPanVat" />
                            </x-field>
                        @endif
                    </div>
                </x-card>

                <x-card title="The order">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-field label="Order amount (Rs.)" for="orderAmount" required
                                 hint="What the vendor is actually being asked for." :error="$errors->first('orderAmount')">
                            <x-input id="orderAmount" type="number" step="0.01" min="0.01"
                                     wire:model.live.debounce.400ms="orderAmount" class="tnum text-right" />
                        </x-field>

                        <x-field label="Expected delivery" for="expectedDate" hint="Optional." :error="$errors->first('expectedDate')">
                            <x-input id="expectedDate" type="date" wire:model="expectedDate" />
                        </x-field>

                        <x-field label="Note" for="note" class="sm:col-span-2" hint="Optional — anything the store keeper should know.">
                            <x-input id="note" wire:model="note" />
                        </x-field>
                    </div>

                    @if (\App\Support\Money::gt($this->overApprovedBy, 0))
                        <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-relaxed text-amber-900 dark:bg-amber-500/10">
                            This order is <strong><x-money :amount="$this->overApprovedBy" /></strong> above the approved amount.
                            You can still place it, but it will be written into the audit trail as such, and the bill will
                            be flagged as a mismatch until Accounts accepts the difference in writing.
                        </p>
                    @endif

                    <p class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:bg-white/5 dark:text-slate-400">
                        This order will be locked to <strong>{{ auth()->user()->full_name }}</strong>.
                        When the goods arrive, somebody else must be the one to verify them — the system will refuse you.
                    </p>
                </x-card>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-button variant="secondary" href="{{ route('orders.index') }}" wire:navigate>Cancel</x-button>
                    <x-button type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Place the order</span>
                        <span wire:loading wire:target="save">Placing…</span>
                    </x-button>
                </div>
            @endif
        </form>
    @endif
</div>
