@props(['class' => 'bg-slate-100 text-slate-700 ring-slate-500/20 dark:bg-white/5 dark:text-slate-400 dark:ring-white/10'])

<span class="inline-flex items-center rounded-md px-2 py-0.5 font-mono text-[10.5px] font-semibold uppercase tracking-wide ring-1 ring-inset {{ $class }}">
    {{ $slot }}
</span>
