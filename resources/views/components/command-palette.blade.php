@props(['items'])

{{--
    Ctrl+K / Cmd+K quick search over every screen this person can reach.
    `cmdk` (open) and `menu` come from the parent shell's Alpine scope; this
    component owns everything else in one x-data block, since a getter's
    `this` does not see a parent component's data the way template
    expressions do.
--}}
<div
    x-data="{
        query: '',
        active: 0,
        items: @js($items),
        get filtered() {
            const q = this.query.trim().toLowerCase();
            return q === '' ? this.items : this.items.filter(i => i.label.toLowerCase().includes(q));
        },
    }"
    x-show="cmdk"
    x-cloak
    x-effect="if (cmdk) { query = ''; active = 0; $nextTick(() => $refs.cmdkInput?.focus()) } else if (active >= filtered.length) { active = Math.max(0, filtered.length - 1) }"
    @keydown.escape.window="cmdk = false"
    @keydown.down.prevent="active = Math.min(active + 1, filtered.length - 1)"
    @keydown.up.prevent="active = Math.max(active - 1, 0)"
    @keydown.enter="if (filtered[active]) window.location.href = filtered[active].url"
    class="fixed inset-0 z-[60] flex items-start justify-center bg-slate-950/60 px-4 pt-[12vh] backdrop-blur-sm"
    @click="cmdk = false"
>
    <div @click.stop class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-900">
        <div class="flex items-center gap-3 border-b border-slate-200 px-4 dark:border-white/10">
            <svg class="size-4.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5A6.5 6.5 0 114 10.5a6.5 6.5 0 0113 0z" />
            </svg>
            <input
                type="text"
                x-ref="cmdkInput"
                x-model="query"
                placeholder="Search screens..."
                class="min-h-0 flex-1 border-0 bg-transparent py-3.5 text-[15px] text-slate-900 placeholder:text-slate-400 focus:ring-0 dark:text-slate-100 dark:placeholder:text-slate-600"
            />
            <kbd class="shrink-0 rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-[10px] text-slate-400 dark:border-white/10 dark:bg-white/5 dark:text-slate-500">ESC</kbd>
        </div>

        <ul class="scroll-thin max-h-80 overflow-y-auto p-2">
            <template x-for="(item, i) in filtered" :key="item.url">
                <li>
                    <a :href="item.url" wire:navigate
                       @click="cmdk = false"
                       @mouseenter="active = i"
                       class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13.5px] font-medium transition"
                       :class="active === i ? 'bg-indigo-50 text-indigo-700 dark:bg-sky-500/10 dark:text-sky-300' : 'text-slate-700 dark:text-slate-300'">
                        <svg class="size-4 shrink-0 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                        <span x-text="item.label"></span>
                    </a>
                </li>
            </template>

            <li x-show="filtered.length === 0" class="px-3 py-8 text-center text-sm text-slate-400 dark:text-slate-600">
                No screens match that search.
            </li>
        </ul>
    </div>
</div>
