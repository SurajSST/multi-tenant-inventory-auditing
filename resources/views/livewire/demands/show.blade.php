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
        {{-- The trail --}}
        <x-card title="Audit Trail" subtitle="Every step, attributed and timestamped" :flush="true">
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-xs dark:divide-white/10">
                    <thead class="bg-slate-50 font-semibold uppercase tracking-wider text-slate-500 dark:bg-white/5 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-3.5 py-2.5 text-left font-medium">Stage</th>
                            <th scope="col" class="px-3 py-2.5 text-left font-medium">Actor</th>
                            <th scope="col" class="px-3.5 py-2.5 text-right font-medium">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        {{-- Raised --}}
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-white/[0.02]">
                            <td class="px-3.5 py-2.5 align-top">
                                <x-badge class="bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-white/10 dark:text-slate-300">Raised</x-badge>
                            </td>
                            <td class="px-3 py-2.5 align-top">
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ $demand->raisedBy->full_name }}</div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $demand->raisedBy->designation }}</div>
                            </td>
                            <td class="px-3.5 py-2.5 text-right align-top tabular-nums text-slate-500 dark:text-slate-400">
                                <div>{{ $demand->created_at->format('d M Y') }}</div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500">{{ $demand->created_at->format('H:i') }}</div>
                            </td>
                        </tr>

                        {{-- Approvals --}}
                        @foreach ($demand->approvals as $approval)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-white/[0.02]">
                                <td class="px-3.5 py-2.5 align-top">
                                    @if ($approval->action === \App\Enums\ApprovalAction::REJECT)
                                        <x-badge class="bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400">
                                            Tier {{ $approval->tier_no }} · Rejected
                                        </x-badge>
                                    @else
                                        <x-badge class="bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400">
                                            Tier {{ $approval->tier_no }} · Approved
                                        </x-badge>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 align-top">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $approval->actor->full_name }}</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $approval->actor->designation }}</div>
                                    @if ($approval->minute_ref || $approval->reason)
                                        <div class="mt-1 rounded bg-slate-50 px-2 py-1 text-[11px] text-slate-600 dark:bg-white/5 dark:text-slate-300">
                                            @if ($approval->minute_ref)
                                                <span class="font-medium text-slate-800 dark:text-slate-200">Minute {{ $approval->minute_ref }}</span>@if ($approval->reason) — @endif
                                            @endif
                                            @if ($approval->reason)
                                                “{{ $approval->reason }}”
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right align-top tabular-nums text-slate-500 dark:text-slate-400">
                                    <div>{{ $approval->acted_at->format('d M Y') }}</div>
                                    <div class="text-[11px] text-slate-400 dark:text-slate-500">{{ $approval->acted_at->format('H:i') }}</div>
                                </td>
                            </tr>
                        @endforeach

                        {{-- Pending Waiting --}}
                        @if ($demand->isPending())
                            <tr class="bg-amber-50/40 hover:bg-amber-50/60 dark:bg-amber-500/5 dark:hover:bg-amber-500/10">
                                <td class="px-3.5 py-2.5 align-top">
                                    <x-badge class="bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400">
                                        Tier {{ $demand->current_tier }} · Waiting
                                    </x-badge>
                                </td>
                                <td class="px-3 py-2.5 align-top">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ $tiers->firstWhere('tier_no', $demand->current_tier)?->decider_label }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">Pending signature</div>
                                </td>
                                <td class="px-3.5 py-2.5 text-right align-top text-slate-400 dark:text-slate-500">
                                    —
                                </td>
                            </tr>
                        @endif

                        {{-- Order Placed --}}
                        @if ($order)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-white/[0.02]">
                                <td class="px-3.5 py-2.5 align-top">
                                    <x-badge class="bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-500/10 dark:text-blue-400">
                                        Ordered · {{ $order->ref }}
                                    </x-badge>
                                </td>
                                <td class="px-3 py-2.5 align-top">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $order->orderedBy->full_name }}</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ $order->vendor->name }} · <x-money :amount="$order->order_amount" /></div>
                                </td>
                                <td class="px-3.5 py-2.5 text-right align-top tabular-nums text-slate-500 dark:text-slate-400">
                                    <div>{{ $order->ordered_at->format('d M Y') }}</div>
                                    <div class="text-[11px] text-slate-400 dark:text-slate-500">{{ $order->ordered_at->format('H:i') }}</div>
                                </td>
                            </tr>
                        @endif

                        {{-- Goods Verified --}}
                        @if ($receipt)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-white/[0.02]">
                                <td class="px-3.5 py-2.5 align-top">
                                    <x-badge class="bg-teal-50 text-teal-700 ring-teal-600/20 dark:bg-teal-500/10 dark:text-teal-400">
                                        Goods Verified
                                    </x-badge>
                                </td>
                                <td class="px-3 py-2.5 align-top">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $receipt->receivedBy->full_name }}</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                        Into {{ $receipt->location->name }} · {{ $receipt->condition->label() }}
                                        @if ($receipt->challan_no) · ch. {{ $receipt->challan_no }} @endif
                                    </div>
                                    @if ($receipt->discrepancy_note)
                                        <div class="mt-1 rounded bg-amber-50 px-2 py-1 text-[11px] text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                                            “{{ $receipt->discrepancy_note }}”
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3.5 py-2.5 text-right align-top tabular-nums text-slate-500 dark:text-slate-400">
                                    <div>{{ $receipt->received_at->format('d M Y') }}</div>
                                    <div class="text-[11px] text-slate-400 dark:text-slate-500">{{ $receipt->received_at->format('H:i') }}</div>
                                </td>
                            </tr>
                        @elseif ($order)
                            <tr class="bg-amber-50/40 hover:bg-amber-50/60 dark:bg-amber-500/5 dark:hover:bg-amber-500/10">
                                <td class="px-3.5 py-2.5 align-top">
                                    <x-badge class="bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400">
                                        Awaiting Receipt
                                    </x-badge>
                                </td>
                                <td class="px-3 py-2.5 align-top" colspan="2">
                                    <div class="text-xs text-slate-600 dark:text-slate-400">
                                        Pending verification by officer other than {{ $order->orderedBy->full_name }}.
                                    </div>
                                </td>
                            </tr>
                        @endif

                        {{-- Bill Entered --}}
                        @if ($bill)
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-white/[0.02]">
                                <td class="px-3.5 py-2.5 align-top">
                                    <x-badge class="bg-indigo-50 text-indigo-700 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-400">
                                        Bill {{ $bill->bill_no }}
                                    </x-badge>
                                </td>
                                <td class="px-3 py-2.5 align-top">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ $bill->enteredBy->full_name }}</div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                        <x-money :amount="$bill->bill_amount" /> · <x-badge :class="$bill->match_status->badge()">{{ $bill->match_status->label() }}</x-badge>
                                    </div>
                                </td>
                                <td class="px-3.5 py-2.5 text-right align-top tabular-nums text-slate-500 dark:text-slate-400">
                                    <div>{{ $bill->entered_at->format('d M Y') }}</div>
                                    <div class="text-[11px] text-slate-400 dark:text-slate-500">{{ $bill->entered_at->format('H:i') }}</div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
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
