@php $receipt = $order->receipt; $bill = $order->bills->first(); @endphp

<div>
    <x-print-header
        title="Purchase Order"
        nepaliTitle="खरीद आदेश"
        :ref="$order->ref"
        :date="$order->ordered_at->format('d M Y')"
        :vendor="$order->vendor->name"
        :status="$order->status->label()"
    />

    <x-page-header :title="$order->ref"
                   :subtitle="$order->vendor->name . ' · against ' . $order->demand->ref">
        <x-slot:actions>
            @can('receive-goods')
                @if (! $receipt && $order->ordered_by_id !== auth()->id())
                    <x-button href="{{ route('orders.receive', $order) }}" wire:navigate>Verify receipt</x-button>
                @endif
            @endcan
            <x-button variant="secondary" href="{{ route('orders.print', ['order' => $order, 'autoprint' => 1]) }}" target="_blank">
                <svg class="size-4 shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656h10.5Z" />
                </svg>
                Print PO
            </x-button>
            <x-button variant="secondary" href="{{ route('orders.pdf', $order) }}">
                <svg class="size-4 shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Download PDF
            </x-button>
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

        <x-card title="Who did what" subtitle="Every step, attributed and timestamped">
            <div class="flow-root">
                <ol class="relative">

                    {{-- Order placed --}}
                    <li class="relative pb-5 pl-9 group last:pb-0">
                        <span class="absolute left-3.5 top-7 -bottom-1 w-0.5 bg-slate-200 dark:bg-white/10 group-last:hidden" aria-hidden="true"></span>
                        <div class="absolute left-0 top-0.5 flex size-7 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-4 ring-white dark:bg-blue-500/20 dark:text-blue-400 dark:ring-slate-900">
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Order placed</span>
                                <time class="text-[11px] text-slate-400 dark:text-slate-500 tabular-nums">{{ $order->ordered_at->format('d M Y, H:i') }}</time>
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-baseline gap-x-1.5 text-xs">
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ $order->orderedBy->full_name }}</span>
                                <span class="text-slate-500 dark:text-slate-400">· {{ $order->orderedBy->designation }}</span>
                            </div>
                            @if ($order->expected_date)
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Expected {{ $order->expected_date->format('d M Y') }}</p>
                            @endif
                        </div>
                    </li>

                    {{-- Goods verified / Awaiting --}}
                    @if ($receipt)
                        <li class="relative pb-5 pl-9 group last:pb-0">
                            <span class="absolute left-3.5 top-7 -bottom-1 w-0.5 bg-slate-200 dark:bg-white/10 group-last:hidden" aria-hidden="true"></span>
                            <div class="absolute left-0 top-0.5 flex size-7 items-center justify-center rounded-full bg-teal-50 text-teal-600 ring-4 ring-white dark:bg-teal-500/20 dark:text-teal-400 dark:ring-slate-900">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.25V3.75c0-.621-.504-1.125-1.125-1.125h-9.75c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-teal-600 dark:text-teal-400">Goods verified</span>
                                    <time class="text-[11px] text-slate-400 dark:text-slate-500 tabular-nums">{{ $receipt->received_at->format('d M Y, H:i') }}</time>
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-baseline gap-x-1.5 text-xs">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $receipt->receivedBy->full_name }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">· Into {{ $receipt->location->name }} · {{ $receipt->condition->label() }}@if ($receipt->challan_no) · ch. {{ $receipt->challan_no }}@endif</span>
                                </div>
                            </div>
                        </li>
                    @else
                        <li class="relative pb-5 pl-9 group last:pb-0">
                            <span class="absolute left-3.5 top-7 -bottom-1 w-0.5 bg-slate-200 dark:bg-white/10 group-last:hidden" aria-hidden="true"></span>
                            <div class="absolute left-0 top-0.5 flex size-7 items-center justify-center rounded-full bg-amber-50 text-amber-600 ring-4 ring-white dark:bg-amber-500/20 dark:text-amber-400 dark:ring-slate-900">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Awaiting verification</span>
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">Pending</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">
                                    Anybody with the Receiving Officer role except {{ $order->orderedBy->full_name }}.
                                </p>
                            </div>
                        </li>
                    @endif

                    {{-- Bill --}}
                    @if ($bill)
                        <li class="relative pb-5 pl-9 group last:pb-0">
                            <span class="absolute left-3.5 top-7 -bottom-1 w-0.5 bg-slate-200 dark:bg-white/10 group-last:hidden" aria-hidden="true"></span>
                            <div class="absolute left-0 top-0.5 flex size-7 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 ring-4 ring-white dark:bg-indigo-500/20 dark:text-indigo-400 dark:ring-slate-900">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v10.5m0-10.5a60.07 60.07 0 0 1 15.797-2.101c.727-.198 1.453.342 1.453 1.096V4.5M3.75 4.5h16.5m-16.5 0v10.5m16.5-10.5v10.5m0-10.5a60.07 60.07 0 0 0-15.797 2.101c-.727.198-1.453-.342-1.453-1.096V15m17.25 0a60.07 60.07 0 0 1-15.797 2.101c-.727.198-1.453-.342-1.453-1.096V15m0 0v2.25" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Bill {{ $bill->bill_no }}</span>
                                    <time class="text-[11px] text-slate-400 dark:text-slate-500 tabular-nums">{{ $bill->entered_at->format('d M Y, H:i') }}</time>
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-baseline gap-x-1.5 text-xs">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $bill->enteredBy->full_name }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">· <x-money :amount="$bill->bill_amount" /> · <x-badge :class="$bill->match_status->badge()">{{ $bill->match_status->label() }}</x-badge></span>
                                </div>
                            </div>
                        </li>
                    @endif
                </ol>
            </div>
        </x-card>
    </div>

    @php
        $orderSignatures = [
            [
                'role' => 'Purchase Officer (आदेशकर्ता)',
                'name' => $order->orderedBy->full_name,
                'designation' => $order->orderedBy->designation,
                'date' => $order->ordered_at->format('d M Y'),
            ],
            [
                'role' => 'Vendor Acknowledgment (विक्रेता)',
                'name' => $order->vendor->name,
                'designation' => 'Authorized Signature & Seal',
                'date' => '_______________',
            ],
            [
                'role' => 'Goods Received By (बुझिलिने)',
                'name' => $receipt ? $receipt->receivedBy->full_name : 'Pending Verification',
                'designation' => $receipt ? $receipt->receivedBy->designation : 'Receiving Officer',
                'date' => $receipt ? $receipt->received_at->format('d M Y') : '_______________',
            ],
            [
                'role' => 'Approved By (स्वीकृतकर्ता)',
                'name' => 'Principal / Executive Head',
                'designation' => 'Prativa Secondary School',
                'date' => $order->ordered_at->format('d M Y'),
            ],
        ];
    @endphp

    <x-print-signatures :signatures="$orderSignatures" />
</div>
