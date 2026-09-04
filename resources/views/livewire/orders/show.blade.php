@php $receipt = $order->receipt; $bill = $order->bills->first(); @endphp

<div>
    <x-page-header :title="$order->ref"
                   :subtitle="$order->vendor->name . ' · against ' . $order->demand->ref">
        <x-slot:actions>
            @can('receive-goods')
                @if (! $receipt && $order->ordered_by_id !== auth()->id())
                    <x-button href="{{ route('orders.receive', $order) }}" wire:navigate>Verify receipt</x-button>
                @endif
            @endcan
            <x-button variant="secondary" href="{{ route('demands.show', $order->demand) }}" wire:navigate>Demand form</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-stat label="Status" :value="$order->status->label()"
                :tone="$order->status === \App\Enums\OrderStatus::RECEIVED ? 'emerald' : 'amber'" />
        <x-stat label="Approved" :value="\App\Support\Money::npr($order->demand->total_amount)" />
        <x-stat label="Ordered" :value="\App\Support\Money::npr($order->order_amount)"
                :tone="\App\Support\Money::gt($order->order_amount, $order->demand->total_amount) ? 'amber' : 'slate'"
                :note="\App\Support\Money::gt($order->order_amount, $order->demand->total_amount)
                    ? \App\Support\Money::npr(\App\Support\Money::sub($order->order_amount, $order->demand->total_amount)) . ' above approval'
                    : 'within the approval'" />
        <x-stat label="Billed" :value="$bill ? \App\Support\Money::npr($bill->bill_amount) : '—'"
                :tone="$bill?->isFlagged() ? 'rose' : 'slate'"
                :note="$bill?->match_status->label() ?? 'no bill entered yet'" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            <x-card title="Lines" :flush="true">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-medium">Item</th>
                                <th scope="col" class="px-4 py-2.5 text-right font-medium">Ordered</th>
                                @if ($receipt)
                                    <th scope="col" class="px-4 py-2.5 text-right font-medium">Received</th>
                                    <th scope="col" class="px-5 py-2.5 font-medium">Remark</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            @foreach ($order->demand->lines as $line)
                                @php $rl = $receipt?->lines->firstWhere('demand_line_id', $line->id) @endphp
                                <tr>
                                    <td class="px-5 py-2.5 text-slate-900 dark:text-slate-100">
                                        {{ $line->item_name }}
                                        @if ($line->specification)
                                            <span class="block text-xs text-slate-500 dark:text-slate-500">{{ $line->specification }}</span>
                                        @endif
                                    </td>
                                    <td class="tnum px-4 py-2.5 text-right text-slate-600 dark:text-slate-400">{{ $line->quantity }}</td>
                                    @if ($receipt)
                                        <td class="tnum px-4 py-2.5 text-right font-medium {{ $rl && $rl->isShort() ? 'text-amber-700 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100' }}">
                                            {{ $rl?->qty_received ?? '—' }}
                                        </td>
                                        <td class="px-5 py-2.5 text-xs text-slate-500 dark:text-slate-500">{{ $rl?->remark }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($order->note)
                <x-card title="Note from the Purchase Officer">
                    <p class="text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $order->note }}</p>
                </x-card>
            @endif

            @if ($receipt?->discrepancy_note)
                <x-card title="Discrepancy recorded on arrival" class="ring-amber-300 dark:ring-amber-500/40">
                    <p class="text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $receipt->discrepancy_note }}</p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-500">
                        Logged by {{ $receipt->receivedBy->full_name }} on {{ $receipt->received_at->format('d M Y, H:i') }}
                    </p>
                    @if ($receipt->attachment_path)
                        <a href="{{ URL::signedRoute('attachments.show', ['path' => $receipt->attachment_path]) }}"
                           target="_blank" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-400">
                            Open the delivery photo
                        </a>
                    @endif
                </x-card>
            @endif
        </div>

        <x-card title="Who did what" :flush="true">
            <ol class="divide-y divide-slate-100 dark:divide-white/5">
                <li class="px-5 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-600">Order placed</p>
                    <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $order->orderedBy->full_name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-500">{{ $order->orderedBy->designation }}</p>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $order->ordered_at->format('d M Y, H:i') }}</p>
                    @if ($order->expected_date)
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">Expected {{ $order->expected_date->format('d M Y') }}</p>
                    @endif
                </li>

                @if ($receipt)
                    <li class="px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-emerald-600">Goods verified</p>
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $receipt->receivedBy->full_name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-500">{{ $receipt->receivedBy->designation }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">
                            Into {{ $receipt->location->name }} · {{ $receipt->condition->label() }}
                            @if ($receipt->challan_no) · challan {{ $receipt->challan_no }} @endif
                        </p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $receipt->received_at->format('d M Y, H:i') }}</p>
                    </li>
                @else
                    <li class="bg-amber-50/60 px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-amber-600 dark:text-amber-400">Awaiting verification</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                            Anybody with the Receiving Officer role except {{ $order->orderedBy->full_name }}.
                        </p>
                    </li>
                @endif

                @if ($bill)
                    <li class="px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-600">Bill {{ $bill->bill_no }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $bill->enteredBy->full_name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-500">
                            <x-money :amount="$bill->bill_amount" /> ·
                            <x-badge :class="$bill->match_status->badge()">{{ $bill->match_status->label() }}</x-badge>
                        </p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $bill->entered_at->format('d M Y, H:i') }}</p>
                    </li>
                @endif
            </ol>
        </x-card>
    </div>
</div>
