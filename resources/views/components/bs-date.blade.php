@props(['date', 'showBsFirst' => true, 'showBoth' => false])

@php
    $bs = \App\Support\NepaliDate::fromAd($date);
    $adStr = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
@endphp

@if ($bs)
    @if ($showBoth)
        <span class="inline-flex items-center gap-1.5 font-mono text-[12px] text-slate-700 dark:text-slate-300">
            <span class="font-semibold text-sky-600 dark:text-sky-400">{{ $bs['formatted'] }} BS</span>
            <span class="text-slate-400 dark:text-slate-600">({{ $adStr }} AD)</span>
        </span>
    @elseif ($showBsFirst)
        <span title="{{ $adStr }} AD" class="cursor-help font-mono text-xs text-slate-700 dark:text-slate-300 underline decoration-slate-300 decoration-dotted underline-offset-2 dark:decoration-slate-700">
            {{ $bs['formatted'] }} BS
        </span>
    @else
        <span title="{{ $bs['formatted'] }} BS ({{ $bs['label'] }})" class="cursor-help font-mono text-xs text-slate-700 dark:text-slate-300 underline decoration-slate-300 decoration-dotted underline-offset-2 dark:decoration-slate-700">
            {{ $adStr }}
        </span>
    @endif
@else
    <span class="font-mono text-xs text-slate-400">—</span>
@endif
