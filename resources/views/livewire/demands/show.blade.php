@php
    $order = $demand->orders->first();
    $receipt = $order?->receipt;
    $bill = $order?->bills->first();
@endphp

<div>
    <x-page-header :title="$demand->ref"
                   :subtitle="$demand->department . ' · raised by ' . $demand->raisedBy->full_name . ', ' . $demand->raisedBy->designation">
        <x-slot:actions>
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
        <x-card title="Trail" subtitle="Every step, attributed and timestamped" :flush="true">
            <ol class="divide-y divide-slate-100 dark:divide-white/5">

                <li class="px-5 py-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-600">Raised</p>
                    <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $demand->raisedBy->full_name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-500">{{ $demand->raisedBy->designation }}</p>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $demand->created_at->format('d M Y, H:i') }}</p>
                </li>

                @foreach ($demand->approvals as $approval)
                    <li class="px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide {{ $approval->action === \App\Enums\ApprovalAction::REJECT ? 'text-rose-500 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            Tier {{ $approval->tier_no }} — {{ $approval->action->label() }}
                        </p>
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $approval->actor->full_name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-500">{{ $approval->actor->designation }}</p>
                        @if ($approval->minute_ref)
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Minute {{ $approval->minute_ref }}</p>
                        @endif
                        @if ($approval->reason)
                            <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">“{{ $approval->reason }}”</p>
                        @endif
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $approval->acted_at->format('d M Y, H:i') }}</p>
                    </li>
                @endforeach

                @if ($demand->isPending())
                    <li class="bg-amber-50/60 px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-amber-600 dark:text-amber-400">Waiting</p>
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">
                            Tier {{ $demand->current_tier }} — {{ $tiers->firstWhere('tier_no', $demand->current_tier)?->decider_label }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-500">Nothing moves until this signature.</p>
                    </li>
                @endif

                @if ($order)
                    <li class="px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-600">Order placed — {{ $order->ref }}</p>
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $order->orderedBy->full_name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-500">{{ $order->vendor->name }} · <x-money :amount="$order->order_amount" /></p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $order->ordered_at->format('d M Y, H:i') }}</p>
                    </li>
                @endif

                @if ($receipt)
                    <li class="px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-600">Goods verified</p>
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $receipt->receivedBy->full_name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-500">
                            Into {{ $receipt->location->name }} · {{ $receipt->condition->label() }}
                            @if ($receipt->challan_no) · challan {{ $receipt->challan_no }} @endif
                        </p>
                        @if ($receipt->discrepancy_note)
                            <p class="mt-1 text-xs leading-relaxed text-amber-800 dark:text-amber-300">“{{ $receipt->discrepancy_note }}”</p>
                        @endif
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $receipt->received_at->format('d M Y, H:i') }}</p>
                    </li>
                @elseif ($order)
                    <li class="bg-amber-50/60 px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-amber-600 dark:text-amber-400">Awaiting receipt</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                            Somebody other than {{ $order->orderedBy->full_name }} has to verify these goods arrived.
                        </p>
                    </li>
                @endif

                @if ($bill)
                    <li class="px-5 py-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-600">Bill entered — {{ $bill->bill_no }}</p>
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
