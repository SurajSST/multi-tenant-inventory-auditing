<div>
    <x-page-header title="Goods Receipts"
                   subtitle="Deliveries verified by the store. Every receipt is linked to its purchase order and verifier.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('orders.index') }}" wire:navigate>Purchase orders</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-5">
        <x-field label="Search receipts" for="receiptSearch" class="max-w-md">
            <x-input id="receiptSearch" type="search" wire:model.live.debounce.300ms="search" placeholder="Order ref, demand ref, or vendor" />
        </x-field>
    </x-card>

    <x-card :flush="true" title="{{ $receipts->total() }} verified receipt{{ $receipts->total() === 1 ? '' : 's' }}">
        @if ($receipts->isEmpty())
            <x-empty title="No receipts found" note="Verified deliveries will appear here." />
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5">
                        <tr>
                            <th class="px-5 py-3 font-medium">Purchase order</th>
                            <th class="px-4 py-3 font-medium">Vendor</th>
                            <th class="px-4 py-3 font-medium">Demand</th>
                            <th class="px-4 py-3 font-medium">Received at</th>
                            <th class="px-4 py-3 font-medium">Condition</th>
                            <th class="px-4 py-3 font-medium">Verified by</th>
                            <th class="px-5 py-3 text-right font-medium">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($receipts as $receipt)
                            <tr wire:key="receipt-{{ $receipt->id }}" class="hover:bg-slate-50 dark:hover:bg-white/[0.03]">
                                <td class="whitespace-nowrap px-5 py-3 font-semibold text-slate-900 dark:text-slate-100">{{ $receipt->purchaseOrder->ref }}</td>
                                <td class="px-4 py-3">{{ $receipt->purchaseOrder->vendor->name }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $receipt->purchaseOrder->demand->ref }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $receipt->received_at->format('d M Y') }}<span class="ml-1 text-xs text-slate-500">{{ $receipt->location->name }}</span></td>
                                <td class="px-4 py-3">{{ $receipt->condition->label() }}</td>
                                <td class="px-4 py-3">{{ $receipt->receivedBy->full_name }}</td>
                                <td class="px-5 py-3 text-right">
                                    <x-button variant="secondary" size="sm" class="min-w-[108px] shrink-0 whitespace-nowrap" href="{{ route('orders.show', $receipt->purchaseOrder) }}" wire:navigate>
                                        View order <span aria-hidden="true">→</span>
                                    </x-button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="border-t border-slate-200 px-5 py-3 dark:border-white/10">{{ $receipts->links() }}</div>
    </x-card>
</div>
