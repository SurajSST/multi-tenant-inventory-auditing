@props(['text', 'label' => 'Copy'])

<span x-data="{ copied: false }" class="inline-flex items-center align-middle">
    <button type="button"
            @click.stop="navigator.clipboard.writeText(@js($text)); copied = true; setTimeout(() => copied = false, 2000)"
            class="inline-flex items-center gap-1 rounded p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-white/10 dark:hover:text-slate-200"
            :title="copied ? 'Copied!' : @js($label)">
        <svg x-show="!copied" class="size-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
        </svg>
        <svg x-show="copied" x-cloak class="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
        <span x-show="copied" x-cloak class="font-mono text-[10px] font-semibold text-emerald-600 dark:text-emerald-400">Copied!</span>
    </button>
</span>
