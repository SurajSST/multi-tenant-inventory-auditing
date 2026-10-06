<div>
    <x-page-header title="Supplier Payments & Disbursements"
                   subtitle="Record of payment vouchers (PV) settled against procurement bills with double-entry general ledger entries.">
        <x-slot:actions>
            <x-button href="{{ route('bills.pay') }}" wire:navigate>Record Payment</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card :flush="true" title="{{ $this->payments->total() }} Payment Voucher{{ $this->payments->total() === 1 ? '' : 's' }}">
        @if ($this->payments->isEmpty())
            <x-empty title="No payment vouchers recorded"
                     note="Payments settled against supplier bills will appear here." />
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 font-medium">Voucher No</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Vendor</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Method</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Date</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Amount</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Allocated Bills</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">Paid by</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($this->payments as $payment)
                            <tr wire:key="p-{{ $payment->id }}" class="hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-5 py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                    {{ $payment->voucher_no }}
                                    @if ($payment->reference_no)
                                        <span class="block text-xs text-slate-500">Ref: {{ $payment->reference_no }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300 font-medium">
                                    {{ $payment->vendor?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-600 dark:text-slate-400">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-700 dark:bg-white/10 dark:text-slate-300">
                                        {{ is_object($payment->payment_method) ? $payment->payment_method->label() : $payment->payment_method }}
                                    </span>
                                    @if ($payment->bank_name)
                                        <span class="block text-[11px] text-slate-400 mt-0.5">{{ $payment->bank_name }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400">
                                    {{ $payment->payment_date ? $payment->payment_date->format('d M Y') : '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold text-slate-900 dark:text-slate-100">
                                    <x-money :amount="$payment->amount" :bare="true" />
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-600 dark:text-slate-400">
                                    @foreach ($payment->allocations as $alloc)
                                        <span class="inline-block mr-1">
                                            {{ $alloc->bill?->bill_no ?? 'Bill' }} ({{ \App\Support\Money::npr($alloc->allocated_amount) }})
                                        </span>
                                    @endforeach
                                </td>
                                <td class="px-5 py-2.5 text-xs text-slate-500 dark:text-slate-500">
                                    {{ $payment->paidBy?->full_name ?? 'Staff' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($this->payments->hasPages())
                <div class="border-t border-slate-100 px-5 py-3 dark:border-white/5">{{ $this->payments->links() }}</div>
            @endif
        @endif
    </x-card>
</div>
