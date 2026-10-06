<div>
    <x-page-header :title="'Return Goods against ' . $this->order->ref"
                   :subtitle="'Vendor: ' . $this->order->vendor->name . ' · Deducts stock from inventory ledger and creates accounting adjustment.'" />

    <x-errors />

    @if ($this->order->receipts->isEmpty())
        <x-card>
            <x-empty title="No verified goods receipts on this order"
                     note="Goods must be verified as delivered before any defective or surplus items can be returned to the supplier.">
                <x-button variant="secondary" href="{{ route('orders.show', $this->order) }}" wire:navigate>Back to order</x-button>
            </x-empty>
        </x-card>
    @else
        <form wire:submit="save" class="space-y-6">
            <x-card title="Select Goods Receipt & Reason">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field label="Receipt Delivery" for="receiptId" required :error="$errors->first('receiptId')">
                        <x-select id="receiptId" wire:model.live="receiptId">
                            @foreach ($this->order->receipts as $r)
                                <option value="{{ $r->id }}">
                                    GRN on {{ $r->received_at->format('d M Y') }} — {{ $r->location->name }} (Challan: {{ $r->challan_no ?? 'N/A' }})
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="General Return Reason" for="reason" required hint="e.g. Defective units, damaged in transit, incorrect specification." :error="$errors->first('reason')">
                        <x-input id="reason" wire:model="reason" placeholder="State the reason for return" />
                    </x-field>
                </div>

                @if ($this->receipt)
                    <div class="mt-5 rounded-lg border border-slate-200 dark:border-white/10">
                        <div class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-white/10 dark:bg-white/5 flex items-center justify-between">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-500">Receipt Lines Available to Return</p>
                            <p class="text-xs text-slate-500">Location: {{ $this->receipt->location->name }}</p>
                        </div>
                        <div class="table-scroll">
                            <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-white/5">
                                <thead>
                                    <tr class="text-xs text-slate-500 uppercase bg-slate-50 dark:bg-white/5">
                                        <th class="px-4 py-2 text-left">Item</th>
                                        <th class="px-4 py-2 text-center">Delivered Qty</th>
                                        <th class="px-4 py-2 text-center w-32">Return Qty</th>
                                        <th class="px-4 py-2 text-left">Specific Note</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                    @foreach ($this->receipt->lines as $line)
                                        <tr>
                                            <td class="px-4 py-2 text-slate-900 dark:text-slate-100">
                                                {{ $line->poLine?->description ?? $line->demandLine?->item_name ?? 'Item' }}
                                                @if ($line->remark)
                                                    <span class="block text-xs text-slate-500">{{ $line->remark }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-center text-slate-600 dark:text-slate-400 font-semibold">
                                                {{ $line->qty_received }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <x-input type="number" min="0" max="{{ $line->qty_received }}"
                                                         wire:model.live.debounce.300ms="returnQty.{{ $line->id }}"
                                                         class="tnum text-center text-sm py-1" />
                                            </td>
                                            <td class="px-4 py-2">
                                                <x-input wire:model="lineReasons.{{ $line->id }}" placeholder="Optional defect note" class="text-xs py-1" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </x-card>

            <div class="flex items-center justify-end gap-3">
                <x-button variant="secondary" href="{{ route('orders.show', $this->order) }}" wire:navigate>Cancel</x-button>
                <x-button type="submit" variant="danger" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Confirm Return to Supplier</span>
                    <span wire:loading wire:target="save">Posting Return…</span>
                </x-button>
            </div>
        </form>
    @endif
</div>
