@php
    $order = $demand->orders->first();
    $receipt = $order?->receipt;
    $bill = $order?->bills->first();
@endphp

<div>
    <x-print-header
        title="Purchase Demand Form"
        nepaliTitle="खरीद माग फाराम"
        :ref="$demand->ref"
        :date="$demand->created_at->format('d M Y')"
        :fiscalYear="$demand->fiscal_year ?? null"
        :department="$demand->department"
        :status="$demand->status->label()"
    />

    <x-page-header :title="$demand->ref"
                   :copyable="$demand->ref"
                   :subtitle="$demand->department . ' · raised by ' . $demand->raisedBy->full_name . ', ' . $demand->raisedBy->designation">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('demands.print', ['demand' => $demand, 'autoprint' => 1]) }}" target="_blank">
                <svg class="size-4 shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656h10.5Z" />
                </svg>
                Print
            </x-button>
            <x-button variant="secondary" href="{{ route('demands.pdf', $demand) }}">
                <svg class="size-4 shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Download PDF
            </x-button>
            @if ($demand->isPending() && ($demand->raised_by_id === auth()->id() || auth()->user()->isSuperAdmin()))
                <x-button variant="secondary" wire:click="withdraw"
                          wire:confirm="Withdraw this demand form? It stops here and cannot be revived.">
                    Withdraw
                </x-button>
            @endif
            <x-button variant="secondary" href="{{ route('demands.index') }}" wire:navigate>All demands</x-button>
        </x-slot:actions>
    </x-page-header>

    @error('withdraw')
        <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-300">{{ $message }}</div>
    @enderror

    {{-- Lifecycle Stepper --}}
    <div class="mb-6 overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-xs dark:border-white/10 dark:bg-slate-900/60 no-print">
        <div class="overflow-x-auto p-4 sm:p-5">
            <div class="flex items-center min-w-[640px] justify-between">
                {{-- Step 1: Raised --}}
                <div class="flex items-center gap-2.5">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white shadow-2xs ring-4 ring-emerald-50 dark:ring-emerald-500/20">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-900 dark:text-white">Raised</p>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $demand->raisedBy->full_name }}</p>
                    </div>
                </div>

                {{-- Connector --}}
                <div class="h-0.5 flex-1 mx-3 bg-slate-200 dark:bg-white/10"></div>

                {{-- Step 2: Approvals --}}
                @php
                    $isRejected = $demand->status === \App\Enums\DemandStatus::REJECTED;
                    $isApproved = $demand->status === \App\Enums\DemandStatus::APPROVED;
                @endphp
                <div class="flex items-center gap-2.5">
                    @if ($isRejected)
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-rose-500 text-white shadow-2xs ring-4 ring-rose-50 dark:ring-rose-500/20">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-rose-600 dark:text-rose-400">Rejected</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">At Tier {{ $demand->approvals->last()?->tier_no }}</p>
                        </div>
                    @elseif ($isApproved)
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white shadow-2xs ring-4 ring-emerald-50 dark:ring-emerald-500/20">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-900 dark:text-white">Fully Approved</p>
                            <p class="text-[11px] text-emerald-600 dark:text-emerald-400">All {{ $demand->final_tier }} tiers signed</p>
                        </div>
                    @else
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white shadow-2xs ring-4 ring-amber-50 dark:ring-amber-500/20 animate-pulse">
                            <span class="font-mono text-xs font-bold">{{ $demand->current_tier }}</span>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-amber-700 dark:text-amber-400">Tier {{ $demand->current_tier }} Deciding</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $tiers->firstWhere('tier_no', $demand->current_tier)?->decider_label ?? 'Approver' }}</p>
                        </div>
                    @endif
                </div>

                {{-- Connector --}}
                <div class="h-0.5 flex-1 mx-3 {{ $order ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-white/10' }}"></div>

                {{-- Step 3: Purchase Order --}}
                <div class="flex items-center gap-2.5">
                    @if ($order)
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white shadow-2xs ring-4 ring-emerald-50 dark:ring-emerald-500/20">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-900 dark:text-white">Ordered</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">{{ $order->ref }}</p>
                        </div>
                    @elseif ($isApproved)
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-sky-500 bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                            <span class="size-2 rounded-full bg-sky-500"></span>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-sky-700 dark:text-sky-300">Ready for PO</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Purchase Officer</p>
                        </div>
                    @else
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-slate-200 text-slate-300 dark:border-white/10 dark:text-slate-600">
                            <span class="size-2 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-400 dark:text-slate-500">Purchase Order</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-600">Pending approval</p>
                        </div>
                    @endif
                </div>

                {{-- Connector --}}
                <div class="h-0.5 flex-1 mx-3 {{ $receipt ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-white/10' }}"></div>

                {{-- Step 4: Receipt / Goods Inward --}}
                <div class="flex items-center gap-2.5">
                    @if ($receipt)
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white shadow-2xs ring-4 ring-emerald-50 dark:ring-emerald-500/20">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-900 dark:text-white">Received</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $receipt->receivedBy->full_name }}</p>
                        </div>
                    @elseif ($order)
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-amber-500 bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                            <span class="size-2 rounded-full bg-amber-500"></span>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">Awaiting Delivery</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Receiving Officer</p>
                        </div>
                    @else
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-slate-200 text-slate-300 dark:border-white/10 dark:text-slate-600">
                            <span class="size-2 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-400 dark:text-slate-500">Goods Inward</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-600">Pending order</p>
                        </div>
                    @endif
                </div>

                {{-- Connector --}}
                <div class="h-0.5 flex-1 mx-3 {{ $bill ? 'bg-emerald-500' : 'bg-slate-200 dark:bg-white/10' }}"></div>

                {{-- Step 5: Billed --}}
                <div class="flex items-center gap-2.5">
                    @if ($bill)
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $bill->isFlagged() ? 'bg-amber-500' : 'bg-emerald-500' }} text-white shadow-2xs ring-4 ring-emerald-50 dark:ring-emerald-500/20">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-900 dark:text-white">Billed</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $bill->match_status->label() }}</p>
                        </div>
                    @elseif ($receipt)
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-indigo-500 bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                            <span class="size-2 rounded-full bg-indigo-500"></span>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-indigo-700 dark:text-indigo-300">Pending Bill</p>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">Accounts</p>
                        </div>
                    @else
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-slate-200 text-slate-300 dark:border-white/10 dark:text-slate-600">
                            <span class="size-2 rounded-full bg-slate-300 dark:bg-slate-700"></span>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-400 dark:text-slate-500">Settlement</p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-600">Pending receipt</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-stat label="Status" :value="$demand->status->label()"
                :tone="$demand->status === \App\Enums\DemandStatus::REJECTED ? 'rose' : ($demand->status === \App\Enums\DemandStatus::APPROVED ? 'emerald' : 'amber')"
                :note="$demand->isPending() ? 'with tier ' . $demand->current_tier : $demand->closed_at?->format('d M Y')" />
        <x-stat label="Total" :value="\App\Support\Money::npr($demand->total_amount)" note="approved value" />
        <x-stat label="Must reach" :value="'Tier ' . $demand->final_tier"
                :note="$tiers->firstWhere('tier_no', $demand->final_tier)?->decider_label" />
        <x-stat label="Raised" :value="$demand->created_at->format('d M Y')" :note="$demand->created_at->format('H:i')" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            <x-card title="What was asked for" :flush="true">
                <div class="border-b border-slate-100 px-5 py-4">
                    <p class="text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $demand->justification }}</p>
                    @if ($demand->need_by_date)
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-500">Needed by {{ $demand->need_by_date->format('d M Y') }}</p>
                    @endif
                </div>

                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-medium">Item</th>
                                <th scope="col" class="px-4 py-2.5 text-right font-medium">Qty</th>
                                <th scope="col" class="px-4 py-2.5 text-right font-medium">Rate</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-medium">Line total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            @foreach ($demand->lines as $line)
                                <tr>
                                    <td class="px-5 py-2.5 text-slate-900 dark:text-slate-100">
                                        {{ $line->item_name }}
                                        @if ($line->itemType)
                                            <code class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-white/10 dark:text-slate-400">{{ $line->itemType->code_prefix }}</code>
                                        @else
                                            <x-badge class="ml-1 bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">not on the register</x-badge>
                                        @endif
                                        @if ($line->specification)
                                            <span class="block text-xs text-slate-500 dark:text-slate-500">{{ $line->specification }}</span>
                                        @endif
                                    </td>
                                    <td class="tnum px-4 py-2.5 text-right text-slate-600 dark:text-slate-400">{{ $line->quantity }}</td>
                                    <td class="px-4 py-2.5 text-right"><x-money :amount="$line->unit_rate" :bare="true" class="text-slate-600 dark:text-slate-400" /></td>
                                    <td class="px-5 py-2.5 text-right"><x-money :amount="$line->line_total" :bare="true" class="font-medium text-slate-900 dark:text-slate-100" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5">
                            <tr>
                                <td colspan="3" class="px-5 py-2.5 text-right text-sm font-medium text-slate-600 dark:text-slate-400">Total</td>
                                <td class="px-5 py-2.5 text-right"><x-money :amount="$demand->total_amount" :bare="true" class="text-sm font-semibold text-slate-900 dark:text-slate-100" /></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>

            {{-- The three-way cross-check, once there is something to compare --}}
            @if ($order)
                <x-card title="Approved ↔ Ordered ↔ Billed"
                        subtitle="The three figures side by side. A bill matches only when it equals the order and does not exceed the approval.">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-white/5">
                            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-500">Approved</p>
                            <x-money :amount="$demand->total_amount" class="mt-1 block text-lg font-semibold text-slate-900 dark:text-slate-100" />
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-white/5">
                            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-500">Ordered</p>
                            <x-money :amount="$order->order_amount" class="mt-1 block text-lg font-semibold {{ \App\Support\Money::gt($order->order_amount, $demand->total_amount) ? 'text-amber-700 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100' }}" />
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-white/5">
                            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-500">Billed</p>
                            @if ($bill)
                                <x-money :amount="$bill->bill_amount" class="mt-1 block text-lg font-semibold {{ $bill->isFlagged() ? 'text-rose-700 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' }}" />
                            @else
                                <p class="mt-1 text-lg font-semibold text-slate-300 dark:text-slate-600">—</p>
                            @endif
                        </div>
                    </div>

                    @if ($bill && ! \App\Support\Money::isZero($bill->variance_amount))
                        <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-500/10">
                            The bill differs from the order by
                            <strong><x-money :amount="\App\Support\Money::abs($bill->variance_amount)" /></strong>.
                            @if ($bill->match_status === \App\Enums\MatchStatus::VARIANCE_CLEARED)
                                {{ $bill->clearedBy->full_name }} accepted it on {{ $bill->cleared_at->format('d M Y') }}:
                                “{{ $bill->variance_note }}”
                            @else
                                It stays flagged until Accounts clears it in writing.
                            @endif
                        </p>
                    @endif
                </x-card>
            @endif
        </div>

        {{-- The trail --}}
        <x-card title="Trail" subtitle="Every step, attributed and timestamped">
            <div class="flow-root">
                <ol class="relative">

                    {{-- Raised --}}
                    <li class="relative pb-5 pl-9 group last:pb-0">
                        <span class="absolute left-3.5 top-7 -bottom-1 w-0.5 bg-slate-200 dark:bg-white/10 group-last:hidden" aria-hidden="true"></span>
                        <div class="absolute left-0 top-0.5 flex size-7 items-center justify-center rounded-full bg-slate-100 text-slate-600 ring-4 ring-white dark:bg-white/10 dark:text-slate-300 dark:ring-slate-900">
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Raised</span>
                                <time class="text-[11px] text-slate-400 dark:text-slate-500 tabular-nums">{{ $demand->created_at->format('d M Y, H:i') }}</time>
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-baseline gap-x-1.5 text-xs">
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ $demand->raisedBy->full_name }}</span>
                                <span class="text-slate-500 dark:text-slate-400">· {{ $demand->raisedBy->designation }}</span>
                            </div>
                        </div>
                    </li>

                    {{-- Approvals --}}
                    @foreach ($demand->approvals as $approval)
                        <li class="relative pb-5 pl-9 group last:pb-0">
                            <span class="absolute left-3.5 top-7 -bottom-1 w-0.5 bg-slate-200 dark:bg-white/10 group-last:hidden" aria-hidden="true"></span>
                            <div class="absolute left-0 top-0.5 flex size-7 items-center justify-center rounded-full {{ $approval->action === \App\Enums\ApprovalAction::REJECT ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400' }} ring-4 ring-white dark:ring-slate-900">
                                @if ($approval->action === \App\Enums\ApprovalAction::REJECT)
                                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                @else
                                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wider {{ $approval->action === \App\Enums\ApprovalAction::REJECT ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                        Tier {{ $approval->tier_no }} — {{ $approval->action->label() }}
                                    </span>
                                    <time class="text-[11px] text-slate-400 dark:text-slate-500 tabular-nums">{{ $approval->acted_at->format('d M Y, H:i') }}</time>
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-baseline gap-x-1.5 text-xs">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $approval->actor->full_name }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">· {{ $approval->actor->designation }}</span>
                                </div>
                                @if ($approval->minute_ref || $approval->reason)
                                    <div class="mt-1.5 rounded-md border border-slate-200/60 bg-slate-50/80 px-2.5 py-1 text-xs text-slate-600 dark:border-white/5 dark:bg-white/5 dark:text-slate-300">
                                        @if ($approval->minute_ref)
                                            <span class="font-medium text-slate-800 dark:text-slate-200">Minute {{ $approval->minute_ref }}</span>@if ($approval->reason) — @endif
                                        @endif
                                        @if ($approval->reason)
                                            “{{ $approval->reason }}”
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach

                    {{-- Pending Waiting --}}
                    @if ($demand->isPending())
                        <li class="relative pb-5 pl-9 group last:pb-0">
                            <span class="absolute left-3.5 top-7 -bottom-1 w-0.5 bg-slate-200 dark:bg-white/10 group-last:hidden" aria-hidden="true"></span>
                            <div class="absolute left-0 top-0.5 flex size-7 items-center justify-center rounded-full bg-amber-50 text-amber-600 ring-4 ring-white dark:bg-amber-500/20 dark:text-amber-400 dark:ring-slate-900">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <span class="absolute -top-0.5 -right-0.5 flex size-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex size-2 rounded-full bg-amber-500"></span>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Waiting</span>
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">Pending</span>
                                </div>
                                <p class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100">
                                    Tier {{ $demand->current_tier }} — {{ $tiers->firstWhere('tier_no', $demand->current_tier)?->decider_label }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Nothing moves until this signature.</p>
                            </div>
                        </li>
                    @endif

                    {{-- Order Placed --}}
                    @if ($order)
                        <li class="relative pb-5 pl-9 group last:pb-0">
                            <span class="absolute left-3.5 top-7 -bottom-1 w-0.5 bg-slate-200 dark:bg-white/10 group-last:hidden" aria-hidden="true"></span>
                            <div class="absolute left-0 top-0.5 flex size-7 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-4 ring-white dark:bg-blue-500/20 dark:text-blue-400 dark:ring-slate-900">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Order placed — {{ $order->ref }}</span>
                                    <time class="text-[11px] text-slate-400 dark:text-slate-500 tabular-nums">{{ $order->ordered_at->format('d M Y, H:i') }}</time>
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-baseline gap-x-1.5 text-xs">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $order->orderedBy->full_name }}</span>
                                    <span class="text-slate-500 dark:text-slate-400">· {{ $order->vendor->name }} · <x-money :amount="$order->order_amount" /></span>
                                </div>
                            </div>
                        </li>
                    @endif

                    {{-- Goods Verified --}}
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
                                @if ($receipt->discrepancy_note)
                                    <p class="mt-1 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">“{{ $receipt->discrepancy_note }}”</p>
                                @endif
                            </div>
                        </li>
                    @elseif ($order)
                        <li class="relative pb-5 pl-9 group last:pb-0">
                            <span class="absolute left-3.5 top-7 -bottom-1 w-0.5 bg-slate-200 dark:bg-white/10 group-last:hidden" aria-hidden="true"></span>
                            <div class="absolute left-0 top-0.5 flex size-7 items-center justify-center rounded-full bg-amber-50 text-amber-600 ring-4 ring-white dark:bg-amber-500/20 dark:text-amber-400 dark:ring-slate-900">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Awaiting receipt</span>
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">Pending</span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">
                                    Somebody other than {{ $order->orderedBy->full_name }} has to verify goods arrived.
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
        $demandSignatures = [
            [
                'role' => 'Demanded By (मागकर्ता)',
                'name' => $demand->raisedBy->full_name,
                'designation' => $demand->raisedBy->designation,
                'date' => $demand->created_at->format('d M Y'),
            ],
        ];

        if ($demand->approvals->isNotEmpty()) {
            foreach ($demand->approvals as $approval) {
                $demandSignatures[] = [
                    'role' => 'Tier ' . $approval->tier_no . ' (' . $approval->action->label() . ')',
                    'name' => $approval->actor->full_name,
                    'designation' => $approval->actor->designation,
                    'date' => $approval->acted_at->format('d M Y'),
                ];
            }
        }

        if ($demand->isPending()) {
            $nextTier = $tiers->firstWhere('tier_no', $demand->current_tier);
            $demandSignatures[] = [
                'role' => 'Recommending (सिफारिसकर्ता)',
                'name' => $nextTier ? $nextTier->decider_label : 'Pending',
                'designation' => 'Tier ' . $demand->current_tier,
                'date' => '_______________',
            ];
            $demandSignatures[] = [
                'role' => 'Final Approval (स्वीकृतकर्ता)',
                'name' => 'Principal / Head of School',
                'designation' => 'Final Tier Approval',
                'date' => '_______________',
            ];
        } elseif ($demand->approvals->where('tier_no', $demand->final_tier)->isEmpty()) {
            $demandSignatures[] = [
                'role' => 'Final Approved By (स्वीकृतकर्ता)',
                'name' => 'Principal / Executive Head',
                'designation' => 'Tier ' . $demand->final_tier,
                'date' => $demand->closed_at?->format('d M Y') ?? '_______________',
            ];
        }
    @endphp

    <x-print-signatures :signatures="$demandSignatures" />
</div>
