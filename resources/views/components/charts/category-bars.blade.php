@props(['data'])

@php
    $total = max($data->sum('units_on_register'), 1);
    $palette = [
        'bg-sky-500',
        'bg-emerald-500',
        'bg-amber-500',
        'bg-indigo-500',
        'bg-rose-500',
        'bg-teal-500',
        'bg-violet-500',
    ];
@endphp

<div class="flex flex-col gap-4">
    {{-- Composite segmented bar --}}
    <div class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100 p-0.5 dark:bg-white/5 shadow-inner">
        @foreach ($data as $i => $row)
            @php $pct = ($row->units_on_register / $total) * 100 @endphp
            <div class="{{ $palette[$i % count($palette)] }} transition-all duration-300 rounded-full"
                 style="width: {{ $pct }}%"
                 title="{{ $row->category }}: {{ number_format($pct, 1) }}% ({{ number_format($row->units_on_register) }} units)"></div>
        @endforeach
    </div>

    {{-- Category breakdown rows --}}
    <div class="flex flex-col divide-y divide-slate-100 dark:divide-white/5">
        @foreach ($data as $i => $row)
            @php $pct = ($row->units_on_register / $total) * 100 @endphp
            <div class="flex items-center justify-between gap-3 py-2 text-[13px] hover:bg-slate-50/50 dark:hover:bg-white/[0.02] px-1 rounded-md transition-colors">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="size-2.5 shrink-0 rounded-full {{ $palette[$i % count($palette)] }} shadow-sm"></span>
                    <span class="truncate font-semibold text-slate-800 dark:text-slate-200">{{ $row->category }}</span>
                    <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500">({{ $row->item_types }} types)</span>
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="tnum font-semibold text-slate-900 dark:text-slate-100">{{ number_format($row->units_on_register) }}</span>
                    <span class="tnum w-12 text-right text-xs font-mono font-medium text-slate-500 dark:text-slate-400">{{ number_format($pct, 1) }}%</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
