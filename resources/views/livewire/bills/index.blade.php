<div>
    <x-page-header title="Bills"
                   subtitle="Approved ↔ ordered ↔ billed, compared live. A bill matches only when it equals the order and does not exceed the approval.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('export.procurement') }}">Export</x-button>
            <x-button href="{{ route('bills.create') }}" wire:navigate>Enter a bill</x-button>
        </x-slot:actions>
    </x-page-header>

    @if ($awaitingBill->isNotEmpty())
        <x-card class="mb-6 ring-sky-300 dark:ring-sky-500/40"
                title="{{ $awaitingBill->count() }} verified deliver{{ $awaitingBill->count() === 1 ? 'y' : 'ies' }} with no bill yet"
                subtitle="Goods confirmed received. The vendor's bill can now be entered against them." :flush="true">
            <ul class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($awaitingBill as $order)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $order->ref }} · {{ $order->vendor->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-500">
                                Verified by {{ $order->receipt->receivedBy->full_name }},
                                {{ $order->receipt->received_at->format('d M Y') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-money :amount="$order->order_amount" class="text-sm font-semibold text-slate-900 dark:text-slate-100" />
                            <x-button variant="secondary" href="{{ route('bills.create', ['purchaseOrderId' => $order->id]) }}" wire:navigate>
                                Enter bill
                            </x-button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card class="mb-5">
        <x-field label="Match status" for="status" class="max-w-xs">
            <x-select id="status" wire:model.live="status">
                <option value="">Every bill</option>
                @foreach (\App\Enums\MatchStatus::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </x-select>
        </x-field>
    </x-card>

    <x-card :flush="true" title="{{ $bills->total() }} bill{{ $bills->total() === 1 ? '' : 's' }}">
        @if ($bills->isEmpty())
            <x-empty title="No bills entered"
                     note="A bill can only be entered once the goods on that order have been verified as received." />
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 font-medium">Bill</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Vendor</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Approved</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Ordered</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Billed</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Difference</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Status</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">Entered by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($bills as $bill)
                            <tr wire:key="bill-{{ $bill->id }}" class="{{ $bill->isFlagged() ? 'bg-rose-50/50 dark:bg-rose-500/10' : '' }} hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-5 py-2.5">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $bill->bill_no }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-500">
                                        {{ $bill->bill_date->format('d M Y') }}
                                        @if ($bill->purchaseOrder) · {{ $bill->purchaseOrder->ref }} @endif
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ $bill->vendor->name }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    @if ($bill->approved_amount)
                                        <x-money :amount="$bill->approved_amount" :bare="true" class="text-slate-500 dark:text-slate-500" />
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @if ($bill->ordered_amount)
                                        <x-money :amount="$bill->ordered_amount" :bare="true" class="text-slate-500 dark:text-slate-500" />
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <x-money :amount="$bill->bill_amount" :bare="true" class="font-semibold text-slate-900 dark:text-slate-100" />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @if (\App\Support\Money::isZero($bill->variance_amount))
                                        <span class="text-slate-300 dark:text-slate-600">—</span>
                                    @else
                                        <span class="tnum font-medium {{ \App\Support\Money::gt($bill->variance_amount, 0) ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">
                                            {{ \App\Support\Money::gt($bill->variance_amount, 0) ? '+' : '' }}{{ \App\Support\Money::format($bill->variance_amount) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <x-badge :class="$bill->match_status->badge()">{{ $bill->match_status->label() }}</x-badge>
                                    @if ($bill->isFlagged())
                                        <button type="button" wire:click="openClear('{{ $bill->id }}')"
                                                class="mt-1 block text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-400">
                                            Clear it
                                        </button>
                                    @elseif ($bill->variance_note)
                                        <span class="mt-1 block max-w-xs text-xs text-slate-500 dark:text-slate-500">
                                            {{ $bill->clearedBy->full_name }}: “{{ Str::limit($bill->variance_note, 70) }}”
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 text-xs text-slate-500 dark:text-slate-500">
                                    {{ $bill->enteredBy->full_name }}
                                    <span class="block text-slate-400 dark:text-slate-600">{{ $bill->entered_at->format('d M Y') }}</span>
                                    @if ($bill->attachment_path)
                                        <a href="{{ URL::signedRoute('attachments.show', ['path' => $bill->attachment_path]) }}"
                                           target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-400">Scan</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($bills->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-white/5">{{ $bills->links() }}</div>
        @endif
    </x-card>

    {{-- Clearing a variance --}}
    @if ($clearing)
        <x-sheet :title="'Accept the difference on ' . $clearing->bill_no" wireClose="closeClear">
            <x-slot:footer>
                <x-button variant="secondary" wire:click="closeClear">Cancel</x-button>
                <x-button wire:click="clear" wire:loading.attr="disabled">Accept the variance</x-button>
            </x-slot:footer>

            <div class="grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-500">Approved</p>
                    <x-money :amount="$clearing->approved_amount" :bare="true" class="text-sm font-semibold text-slate-900 dark:text-slate-100" />
                </div>
                <div class="rounded-lg bg-slate-50 p-3 dark:bg-white/5">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-500">Ordered</p>
                    <x-money :amount="$clearing->ordered_amount" :bare="true" class="text-sm font-semibold text-slate-900 dark:text-slate-100" />
                </div>
                <div class="rounded-lg bg-rose-50 p-3 dark:bg-rose-500/10">
                    <p class="text-[11px] uppercase tracking-wide text-rose-600 dark:text-rose-400">Billed</p>
                    <x-money :amount="$clearing->bill_amount" :bare="true" class="text-sm font-semibold text-rose-800 dark:text-rose-300" />
                </div>
            </div>

            <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-600 dark:bg-white/5 dark:text-slate-400">
                Nothing is erased. The three figures above stay on record; your reason and your name are attached
                permanently, and the entry cannot be edited afterwards.
            </p>

            <div class="mt-5">
                <x-field label="Why is this difference acceptable?" for="varianceNote" required
                         hint="At least a sentence. This is what an auditor will read."
                         :error="$errors->first('varianceNote')">
                    <x-textarea id="varianceNote" wire:model="varianceNote" rows="3" />
                </x-field>
            </div>
        </x-sheet>
    @endif
</div>
