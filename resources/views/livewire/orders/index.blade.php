<div>
    <x-page-header title="Purchase Orders"
                   subtitle="Every order, who placed it, and who verified that it arrived. Those are never the same person.">
        <x-slot:actions>
            @can('place-orders')
                <x-button href="{{ route('orders.create') }}" wire:navigate>Place an order</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Approved demands with nothing ordered against them yet --}}
    @if ($awaitingOrder->isNotEmpty())
        <x-card class="mb-6 ring-1 ring-sky-300 dark:ring-sky-500/40"
                title="{{ $awaitingOrder->count() }} approved demand{{ $awaitingOrder->count() === 1 ? '' : 's' }} with no order yet"
                subtitle="Fully signed off and waiting for the Purchase Officer." :flush="true">
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-sky-50/50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-sky-500/5 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 font-medium">Demand Ref</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Department</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Raised By</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Approved Date</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Approved Amount</th>
                            <th scope="col" class="px-5 py-2.5 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($awaitingOrder as $demand)
                            <tr wire:key="awaiting-{{ $demand->id }}" class="hover:bg-slate-50 dark:hover:bg-white/[0.03] transition-colors">
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <a href="{{ route('demands.show', $demand) }}" wire:navigate
                                       class="font-semibold text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-sky-400">
                                        {{ $demand->ref }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-700 dark:text-slate-300">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 dark:bg-white/10">
                                        {{ $demand->department }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-slate-700 dark:text-slate-300">
                                    {{ $demand->raisedBy->full_name }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                    {{ $demand->closed_at?->format('d M Y, H:i') ?? $demand->updated_at->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap font-semibold">
                                    <x-money :amount="$demand->total_amount" class="text-slate-900 dark:text-slate-100" />
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @can('place-orders')
                                        <x-button size="xs" href="{{ route('orders.create', ['demandId' => $demand->id]) }}" wire:navigate>
                                            Order this
                                        </x-button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <x-card class="mb-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Status" for="status">
                <x-select id="status" wire:model.live="status">
                    <option value="">Every status</option>
                    @foreach (\App\Enums\OrderStatus::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <div class="flex items-end">
                <label class="flex items-center gap-2 pb-2 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" wire:model.live="pendingReceipt"
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                    Only those still awaiting verification
                </label>
            </div>
        </div>
    </x-card>

    <x-card :flush="true" title="{{ $orders->total() }} order{{ $orders->total() === 1 ? '' : 's' }}">
        @if ($orders->isEmpty())
            <x-empty title="No orders yet"
                     note="An order can only be placed against a fully approved demand form." />
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">Order Ref</th>
                            <th scope="col" class="px-4 py-3 font-medium">Against Demand</th>
                            <th scope="col" class="px-4 py-3 font-medium">Vendor</th>
                            <th scope="col" class="px-4 py-3 font-medium">Ordered By</th>
                            <th scope="col" class="px-4 py-3 font-medium">Goods Verification</th>
                            <th scope="col" class="px-4 py-3 text-right font-medium">Order Amount</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($orders as $order)
                            <tr wire:key="order-{{ $order->id }}" class="hover:bg-slate-50 dark:hover:bg-white/[0.03] transition-colors">
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <a href="{{ route('orders.show', $order) }}" wire:navigate
                                       class="font-semibold text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-sky-400">
                                        {{ $order->ref }}
                                    </a>
                                    <span class="block text-xs text-slate-400 dark:text-slate-500">
                                        {{ $order->ordered_at->format('d M Y') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <a href="{{ route('demands.show', $order->demand_id) }}" wire:navigate
                                       class="font-medium text-slate-800 hover:text-indigo-600 dark:text-slate-200 dark:hover:text-sky-400">
                                        {{ $order->demand->ref }}
                                    </a>
                                    <span class="block text-xs text-slate-400 dark:text-slate-500">{{ $order->demand->department }}</span>
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-slate-800 dark:text-slate-200 font-medium">
                                    {{ $order->vendor->name }}
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $order->orderedBy->full_name }}</span>
                                    @if ($order->orderedBy->designation)
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $order->orderedBy->designation }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    @if ($order->receipt)
                                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                            </svg>
                                            Verified by {{ $order->receipt->receivedBy->full_name }}
                                        </span>
                                        <span class="block text-[11px] text-slate-400 dark:text-slate-500">
                                            {{ $order->receipt->received_at->format('d M Y') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">
                                            Awaiting verification
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right whitespace-nowrap font-semibold">
                                    <x-money :amount="$order->order_amount" class="text-slate-900 dark:text-slate-100" />
                                </td>
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <x-badge :class="$order->status->badge()">{{ $order->status->label() }}</x-badge>
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-2">
                                        @can('receive-goods')
                                            @if (! $order->receipt && $order->ordered_by_id !== auth()->id())
                                                <x-button size="xs" href="{{ route('orders.receive', $order) }}" wire:navigate>
                                                    Verify
                                                </x-button>
                                            @endif
                                        @endcan
                                        <x-button variant="secondary" size="xs" href="{{ route('orders.show', $order) }}" wire:navigate>
                                            View
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($orders->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-white/5">{{ $orders->links() }}</div>
        @endif
    </x-card>
</div>
