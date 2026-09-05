<div>
    <x-print-header
        title="Stock Variance Audit Report"
        nepaliTitle="मौज्दात फरक लेखापरीक्षण प्रतिवेदन"
        :date="now()->format('d M Y')"
    />

    <x-page-header title="Stock Variance"
                   subtitle="What the school bought through this system against what is physically on the shelf. A large gap is the first thing worth asking about.">
        <x-slot:actions>
            <x-button variant="secondary" onclick="window.print()">
                <svg class="size-4 shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656h10.5Z" />
                </svg>
                Print Report
            </x-button>
            <x-button variant="secondary" href="{{ route('inventory.register') }}" wire:navigate>Register</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-5 no-print">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field label="Block" for="locationId">
                <x-select id="locationId" wire:model.live="locationId">
                    <option value="">Every block</option>
                    @foreach ($blocks as $block)
                        <option value="{{ $block->id }}">{{ $block->name }}</option>
                    @endforeach
                </x-select>
            </x-field>

            <div class="flex items-end">
                <label class="flex items-center gap-2 pb-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" wire:model.live="onlyDiscrepancies"
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                    Show only rows that do not agree
                </label>
            </div>
        </div>
    </x-card>

    <x-card :flush="true" title="{{ $rows->count() }} row{{ $rows->count() === 1 ? '' : 's' }}">
        @if ($rows->isEmpty())
            <x-empty title="Nothing to compare yet"
                     :note="$onlyDiscrepancies
                        ? 'Every counted figure agrees with what was purchased through the system.'
                        : 'Variance appears once stock is counted or goods are received against an order.'" />
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 font-medium">Item</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Block</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Bought through the system</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Physically counted</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Difference</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">Last counted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            @php $variance = (int) $row->variance @endphp
                            <tr class="{{ $variance !== 0 ? 'bg-amber-50/40 dark:bg-amber-500/10' : '' }} hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-5 py-2.5">
                                    <span class="font-medium text-slate-900 dark:text-slate-100">{{ $row->item }}</span>
                                    <code class="ml-1.5 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600 dark:bg-white/10 dark:text-slate-400">{{ $row->code_prefix }}</code>
                                </td>
                                <td class="px-4 py-2.5 text-slate-700 dark:text-slate-300">{{ $row->block }}</td>
                                <td class="tnum px-4 py-2.5 text-right text-slate-600 dark:text-slate-400">{{ $row->purchased_through_system }}</td>
                                <td class="tnum px-4 py-2.5 text-right font-medium text-slate-900 dark:text-slate-100">{{ $row->physically_counted }}</td>
                                <td class="tnum px-4 py-2.5 text-right font-semibold {{ $variance === 0 ? 'text-slate-400 dark:text-slate-600' : ($variance > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400') }}">
                                    {{ $variance > 0 ? '+' : '' }}{{ $variance }}
                                </td>
                                <td class="px-5 py-2.5 text-xs text-slate-500 dark:text-slate-500">
                                    {{ $row->last_counted_at ? \Illuminate\Support\Carbon::parse($row->last_counted_at)->format('d M Y') : 'never' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs leading-relaxed text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-500">
                A positive difference means more was counted than this system paid for — stock that predates the system,
                a donation, or a transfer. A negative difference means goods were bought but are not on the shelf,
                and that is the one worth chasing.
            </p>
        @endif
    </x-card>

    @php
        $varianceSignatures = [
            [
                'role' => 'Audited By (लेखापरीक्षक)',
                'name' => auth()->user()?->full_name,
                'designation' => auth()->user()?->designation ?? 'Stock Auditor',
                'date' => now()->format('d M Y'),
            ],
            [
                'role' => 'Store In-charge (भण्डार प्रमुख)',
                'name' => 'Store Keeper / Custodian',
                'designation' => 'Inventory Management',
                'date' => '_______________',
            ],
            [
                'role' => 'Verified / Approved By (स्वीकृतकर्ता)',
                'name' => 'Principal / School Head',
                'designation' => 'Prativa Secondary School',
                'date' => now()->format('d M Y'),
            ],
        ];
    @endphp

    <x-print-signatures :signatures="$varianceSignatures" />
</div>
