<div>
    <x-page-header title="Trial Balance (वासलात सूची)"
                   subtitle="Verification of double-entry ledger equilibrium. Total Debits must equal Total Credits.">
        <x-slot:actions>
            <div class="flex items-center gap-3">
                <input type="date" wire:model.live="asOfDate"
                       class="rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" />
            </div>
        </x-slot:actions>
    </x-page-header>

    @php
        $data = $this->report;
        $isBalanced = $data['is_balanced'];
    @endphp

    @if (! $isBalanced)
        <div class="mb-5 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-200">
            <strong>Warning: Out of Balance!</strong> Total debits ({{ \App\Support\Money::npr($data['total_debit']) }}) do not match total credits ({{ \App\Support\Money::npr($data['total_credit']) }}). Difference: {{ \App\Support\Money::npr($data['variance']) }}.
        </div>
    @else
        <div class="mb-5 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200 flex items-center gap-2">
            <svg class="size-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>The General Ledger is perfectly balanced (Total Debits = Total Credits).</span>
        </div>
    @endif

    <x-card :flush="true" title="Accounts Summary as of {{ \Carbon\Carbon::parse($this->asOfDate)->format('d M Y') }}">
        <div class="table-scroll">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-2.5 font-medium">Code</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Account Name</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Classification</th>
                        <th scope="col" class="px-4 py-2.5 text-right font-medium">Debit (NPR)</th>
                        <th scope="col" class="px-4 py-2.5 text-right font-medium">Credit (NPR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @foreach ($data['accounts'] as $row)
                        @php
                            $acc = $row['account'];
                            $hasBalance = ! \App\Support\Money::isZero($row['debit']) || ! \App\Support\Money::isZero($row['credit']);
                        @endphp
                        <tr class="{{ $hasBalance ? 'font-medium' : 'text-slate-400 dark:text-slate-600' }}">
                            <td class="px-5 py-2.5 font-mono text-xs">{{ $acc->code }}</td>
                            <td class="px-4 py-2.5 text-slate-900 dark:text-slate-100">{{ $acc->name }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $acc->type->label() }}</td>
                            <td class="px-4 py-2.5 text-right font-mono">
                                @if (! \App\Support\Money::isZero($row['debit']))
                                    <x-money :amount="$row['debit']" :bare="true" />
                                @else
                                    <span class="text-slate-300 dark:text-slate-700">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono">
                                @if (! \App\Support\Money::isZero($row['credit']))
                                    <x-money :amount="$row['credit']" :bare="true" />
                                @else
                                    <span class="text-slate-300 dark:text-slate-700">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-100 font-bold text-slate-900 dark:bg-white/10 dark:text-slate-100 border-t-2 border-slate-300 dark:border-white/20">
                    <tr>
                        <td colspan="3" class="px-5 py-3 text-right uppercase tracking-wider text-xs">Total Equilibrium:</td>
                        <td class="px-4 py-3 text-right font-mono text-sm">
                            <x-money :amount="$data['total_debit']" :bare="true" />
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-sm">
                            <x-money :amount="$data['total_credit']" :bare="true" />
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-card>
</div>
