@props(['options' => [], 'placeholder' => 'Select an option'])
@php($model = $attributes->wire('model')->value())

<div x-data="{
    options: @js(collect($options)->values()),
    value: @entangle($model).live,
    open: false,
    query: '',
    get label() { return this.options.find(option => String(option.value) === String(this.value))?.label || ''; },
    get matches() {
        const term = this.query.trim().toLowerCase();
        return this.options.filter(option => !term || option.label.toLowerCase().includes(term)).slice(0, 80);
    },
    choose(option) { this.value = option.value; this.open = false; this.query = ''; },
}"
    @click.outside="open = false" class="relative">
    <button type="button" {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'flex w-full items-center justify-between gap-3 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm text-slate-800 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100']) }}
            @click="open = !open; if (open) $nextTick(() => $refs.search.focus())"
            :aria-expanded="open" aria-haspopup="listbox">
        <span class="truncate" x-text="label || @js($placeholder)" :class="label ? '' : 'text-slate-400'"></span>
        <svg class="size-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.22 7.47a.75.75 0 0 1 1.06 0L10 11.19l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" /></svg>
    </button>
    <div x-cloak x-show="open" x-transition.origin.top.left class="absolute z-40 mt-1 w-full min-w-64 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-slate-900">
        <input x-ref="search" x-model="query" type="search" autocomplete="off" placeholder="Type to search…"
               class="w-full border-0 border-b border-slate-200 bg-transparent px-3 py-2.5 text-sm focus:ring-0 dark:border-white/10 dark:text-slate-100" />
        <div role="listbox" class="max-h-60 overflow-y-auto p-1">
            <button type="button" x-show="!query" @click="choose({value: '', label: ''})" class="w-full rounded-md px-3 py-2 text-left text-sm text-slate-500 hover:bg-slate-50 dark:hover:bg-white/5">{{ $placeholder }}</button>
            <template x-for="option in matches" :key="option.value">
                <button type="button" role="option" :aria-selected="String(value) === String(option.value)" @click="choose(option)"
                        class="block w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-indigo-50 aria-selected:bg-indigo-50 aria-selected:text-indigo-700 dark:text-slate-200 dark:hover:bg-white/5 dark:aria-selected:bg-sky-500/10 dark:aria-selected:text-sky-300">
                    <span x-text="option.label"></span>
                </button>
            </template>
            <p x-show="matches.length === 0" class="px-3 py-3 text-sm text-slate-500">No matches found.</p>
        </div>
    </div>
</div>
