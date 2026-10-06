<div>
    <x-page-header title="Supplier Returns"
                   subtitle="Immutable ledger of goods returned to vendors, inventory reversals, and accounts payable adjustments." />

    @if ($this->returns->isEmpty())
        <x-card>
            <x-empty title="No supplier returns recorded"
                     note="Returns are initiated from an order's verified goods receipts whenever defective or excess goods are sent back.">
                <x-button variant="secondary" href="{{ route('orders.index') }}" wire:navigate>View Purchase Orders</x-button>
            </x-empty>
        </x-card>
    @else
        <x-card :flush="true">
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Return Ref</th>
                            <th scope="col" class="px-4 py-3 font-medium">Date</th>
                            <th scope="col" class="px-4 py-3 font-medium">Vendor</th>
                            <th scope="col" class="px-4 py-3 font-medium">Purchase Order</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium">Amount</th>
                            <th scope="col" class="px-4 py-3 font-medium">Reason</th>
                            <th scope="col" class="px-5 py-3 font-medium">Returned By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($this->returns as $ret)
                            <tr wire:key="sr-{{ $ret->id }}">
                                <td class="px-5 py-3 font-semibold text-slate-900 dark:text-slate-100">
                                    {{ $ret->ref }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    {{ $ret->returned_at ? $ret->returned_at->format('d M Y') : '—' }}
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                                    {{ $ret->vendor?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($ret->purchaseOrder)
                                        <a href="{{ route('orders.show', $ret->purchaseOrder) }}" class="text-indigo-600 hover:underline dark:text-sky-400" wire:navigate>
                                            {{ $ret->purchaseOrder->ref }}
                                        </a>
                                    @else
                                        <span class="text-slate-400">N/A</span>
                                    @endif
                                </td>
                                <td class="tnum px-4 py-3 text-right font-semibold text-rose-600 dark:text-rose-400">
                                    -{{ \App\Support\Money::npr($ret->total_amount) }}
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                                    {{ $ret->reason }}
                                </td>
                                <td class="px-5 py-3 text-slate-600 dark:text-slate-400">
                                    {{ $ret->returnedBy?->full_name ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($this->returns->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-white/10">
                    {{ $this->returns->links() }}
                </div>
            @endif
        </x-card>
    @endif
</div>
