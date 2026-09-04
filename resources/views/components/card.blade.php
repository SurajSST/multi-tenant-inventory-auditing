@props(['title' => null, 'subtitle' => null, 'flush' => false])

<section {{ $attributes->merge(['class' =>
    'overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 transition-colors '.
    'dark:bg-slate-900 dark:ring-white/10'
]) }}>
    @if ($title)
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-black/[.015] px-5 py-3.5 dark:border-white/10 dark:bg-white/[.02]">
            <div>
                <h2 class="text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2 no-print">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="{{ $flush ? '' : 'p-5' }}">{{ $slot }}</div>
</section>
