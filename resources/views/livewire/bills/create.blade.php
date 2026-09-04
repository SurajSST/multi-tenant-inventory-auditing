<div>
    <x-page-header title="Enter a Bill"
                   subtitle="A bill can only be entered once the goods have been verified as received. The bill number must be unique across the whole system — the same bill can never be claimed twice." />

    <x-errors />

    <form wire:submit="save" class="space-y-6">

        <x-card title="Against which delivery?"
                subtitle="Leave this blank only for a bill with no purchase order behind it — there will be nothing to match it against.">
            <x-field label="Purchase order" for="purchaseOrderId" :error="$errors->first('purchaseOrderId')">
                <x-select id="purchaseOrderId" wire:model.live="purchaseOrderId">
                    <option value="">No purchase order</option>
                    @foreach ($this->awaiting as $order)
                        <option value="{{ $order->id }}">
                            {{ $order->ref }} — {{ $order->vendor->name }} — {{ \App\Support\Money::npr($order->order_amount) }}
                        </option>
                    @endforeach
                </x-select>
            </x-field>

            @if ($this->order)
                <div class="mt-5 grid grid-cols-2 gap-3 text-center sm:grid-cols-4">
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-500">Approved</p>
                        <x-money :amount="$this->order->demand->total_amount" :bare="true" class="text-sm font-semibold text-slate-900 dark:text-slate-100" />
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-500">Ordered</p>
                        <x-money :amount="$this->order->order_amount" :bare="true" class="text-sm font-semibold text-slate-900 dark:text-slate-100" />
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-500">Verified by</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $this->order->receipt->receivedBy->full_name }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                        <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-500">Vendor</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $this->order->vendor->name }}</p>
                    </div>
                </div>
            @endif
        </x-card>

        <x-card title="The bill">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field label="Bill number" for="billNo" required
                         hint="Exactly as printed on the vendor's bill. Unique across the whole system."
                         :error="$errors->first('billNo')">
                    <x-input id="billNo" wire:model="billNo" />
                </x-field>

                <x-field label="Bill date" for="billDate" required :error="$errors->first('billDate')">
                    <x-input id="billDate" type="date" wire:model="billDate" />
                </x-field>

                <x-field label="Bill amount (Rs.)" for="billAmount" required :error="$errors->first('billAmount')">
                    <x-input id="billAmount" type="number" step="0.01" min="0.01"
                             wire:model.live.debounce.400ms="billAmount" class="tnum text-right" />
                </x-field>

                <x-field label="VAT included (Rs.)" for="vatAmount" hint="Optional." :error="$errors->first('vatAmount')">
                    <x-input id="vatAmount" type="number" step="0.01" min="0" wire:model="vatAmount" class="tnum text-right" />
                </x-field>

                @unless ($this->order)
                    <x-field label="Existing vendor" for="vendorId" hint="Leave blank to add a new one.">
                        <x-select id="vendorId" wire:model.live="vendorId">
                            <option value="">New vendor</option>
                            @foreach ($this->vendors as $vendor)
                                <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    @unless ($vendorId)
                        <x-field label="Vendor name" for="vendorName" required :error="$errors->first('vendorName')">
                            <x-input id="vendorName" wire:model="vendorName" />
                        </x-field>
                    @endunless
                @endunless

                <x-field label="Scan of the bill" for="scan" class="sm:col-span-2"
                         hint="Optional but strongly recommended. Image or PDF."
                         :error="$errors->first('scan')">
                    <input id="scan" type="file" wire:model="scan" accept="image/*,application/pdf"
                           class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-400 dark:file:bg-white/10 dark:file:text-slate-300 dark:hover:file:bg-white/20" />
                    <div wire:loading wire:target="scan" class="mt-1 text-xs text-slate-500 dark:text-slate-500">Uploading…</div>
                </x-field>
            </div>

            @if ($this->willMatch === true)
                <p class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/10">
                    This will match: it equals the order and does not exceed the approval.
                </p>
            @elseif ($this->willMatch === false)
                <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-relaxed text-amber-900 dark:bg-amber-500/10">
                    This will be flagged as a <strong>mismatch</strong> — it differs from
                    {{ \App\Support\Money::npr($this->order->order_amount) }} ordered.
                    Enter it anyway if that is what the vendor billed; it stays flagged until Accounts accepts the
                    difference in writing, and the original figures are never overwritten.
                </p>
            @endif
        </x-card>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-button variant="secondary" href="{{ route('bills.index') }}" wire:navigate>Cancel</x-button>
            <x-button type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">Enter the bill</span>
                <span wire:loading wire:target="save">Saving…</span>
            </x-button>
        </div>
    </form>
</div>
