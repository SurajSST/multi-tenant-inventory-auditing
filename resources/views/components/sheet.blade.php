@props(['title', 'wireClose' => null])

{{--
    A bottom sheet on a phone, a centred dialog on a desktop. Same markup.
    Pass wire:close="methodName" via $wireClose so backdrop-click and Escape
    both close it through the Livewire component that owns the open/close state.
--}}
<div
    x-data
    @keydown.escape.window="$wire.{{ $wireClose }}()"
    class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/60 backdrop-blur-sm lg:items-center lg:p-6"
    @click="$wire.{{ $wireClose }}()"
>
    <div
        @click.stop
        class="animate-sheet-rise flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border-t border-slate-200 bg-white shadow-xl lg:rounded-2xl lg:border dark:border-white/10 dark:bg-slate-900"
    >
        <div class="mx-auto mt-2.5 h-1 w-9 shrink-0 rounded-full bg-slate-300 lg:hidden dark:bg-slate-700"></div>

        <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-5 py-3.5 dark:border-white/10">
            <h3 class="text-base font-bold tracking-tight text-slate-900 dark:text-white">{{ $title }}</h3>
            <button type="button" wire:click="{{ $wireClose }}" aria-label="Close"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-slate-200">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </header>

        <div class="scroll-thin flex-1 overflow-y-auto p-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="flex shrink-0 items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3.5 dark:border-white/10 dark:bg-white/[.02]">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
