@props(['label', 'value', 'note' => null, 'tone' => 'slate', 'href' => null, 'trend' => null])

@php
    $tones = [
        'slate' => 'text-slate-900 dark:text-slate-100',
        'amber' => 'text-amber-700 dark:text-amber-400',
        'rose' => 'text-rose-700 dark:text-rose-400',
        'emerald' => 'text-emerald-700 dark:text-emerald-400',
        'sky' => 'text-sky-700 dark:text-sky-400',
    ];

    $trendClasses = [
        'up' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'down' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400',
        'neutral' => 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400',
    ];
@endphp

<{{ $href ? 'a' : 'div' }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' =>
        'group relative flex flex-col justify-between rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-900/5 transition-all '.
        'dark:bg-slate-900 dark:ring-white/10 '.
        ($href ? 'hover:-translate-y-0.5 hover:shadow-md hover:ring-indigo-300 dark:hover:ring-sky-500/40' : '')
    ]) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="font-mono text-[10.5px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-500">{{ $label }}</p>
        @if ($trend)
            <span class="tnum inline-flex items-center rounded px-1.5 py-0.5 text-[10.5px] font-semibold {{ $trendClasses[$trend['direction']] ?? $trendClasses['neutral'] }}">
                {{ $trend['text'] }}
            </span>
        @endif
    </div>
    <p class="tnum mt-1.5 overflow-hidden text-ellipsis whitespace-nowrap text-2xl font-bold {{ $tones[$tone] }}">{{ $value }}</p>
    @if ($note)
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-500">{{ $note }}</p>
    @endif
</{{ $href ? 'a' : 'div' }}>
