<div
    x-data="{
        toasts: [],
        add(input, defaultTone = 'ok', defaultTitle = null, defaultDuration = 4000) {
            let item = input;
            if (Array.isArray(item)) {
                item = item[0] || {};
            }
            let message = '';
            let tone = defaultTone;
            let title = defaultTitle;
            let duration = defaultDuration;

            if (typeof item === 'object' && item !== null) {
                message = item.message || item.text || item.status || (typeof item === 'string' ? item : JSON.stringify(item));
                tone = item.tone || item.type || defaultTone || 'ok';
                title = item.title || defaultTitle;
                duration = item.duration || defaultDuration || 4000;
            } else {
                message = item;
            }
            if (!message) return;

            const messageStr = String(message).trim();
            if (!messageStr) return;

            // Prevent duplicate toasts with the exact same message from stacking
            if (this.toasts.some(t => t.message === messageStr)) {
                return;
            }

            const id = Date.now() + Math.random();
            const toneNormalized = (tone === 'success' ? 'ok' : (tone === 'error' ? 'bad' : tone)) || 'ok';
            const defaultTitleDerived = title || (toneNormalized === 'bad' ? 'Error' : (toneNormalized === 'warn' ? 'Notice' : (toneNormalized === 'ok' ? 'Success' : 'Notification')));

            const toast = {
                id,
                message: messageStr,
                tone: toneNormalized,
                title: defaultTitleDerived,
                duration: duration || 4000,
                progress: 100,
                paused: false,
                timer: null,
            };

            this.toasts.push(toast);

            const interval = 25;
            const step = (interval / toast.duration) * 100;

            toast.timer = setInterval(() => {
                if (!toast.paused) {
                    toast.progress -= step;
                    if (toast.progress <= 0) {
                        clearInterval(toast.timer);
                        this.remove(toast.id);
                    }
                }
            }, interval);
        },
        remove(id) {
            const idx = this.toasts.findIndex(t => t.id === id);
            if (idx !== -1) {
                if (this.toasts[idx].timer) clearInterval(this.toasts[idx].timer);
                this.toasts.splice(idx, 1);
            }
        },
        init() {
            window.toast = (msg, tone = 'ok', title = null, duration = 4000) => {
                window.dispatchEvent(new CustomEvent('toast', { detail: { message: msg, tone, title, duration } }));
            };

            // Livewire dispatches bubble as window events too, so subscribe only
            // through Livewire here to avoid displaying each toast twice.
            if (window.Livewire) {
                window.Livewire.on('toast', (data) => {
                    const payload = Array.isArray(data) ? data[0] : data;
                    this.add(payload);
                });
                window.Livewire.on('notify', (data) => {
                    const payload = Array.isArray(data) ? data[0] : data;
                    this.add(payload);
                });
            }

            const params = new URLSearchParams(window.location.search);
            if (params.get('notice') === 'password-updated') {
                this.add('Your password has been changed.', 'success', 'Password updated');
                params.delete('notice');
                const query = params.toString();
                window.history.replaceState({}, document.title, window.location.pathname + (query ? `?${query}` : '') + window.location.hash);
            }
        }
    }"
    class="pwa-toast pointer-events-none fixed inset-x-3 top-3 z-[9999] flex flex-col items-center gap-2.5 sm:inset-x-auto sm:right-6 sm:top-6 sm:items-end no-print"
    aria-live="polite"
>
    <template x-for="t in toasts" :key="t.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-3 scale-95 sm:translate-x-4 sm:translate-y-0"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-2 sm:translate-x-4 sm:translate-y-0"
            @mouseenter="t.paused = true"
            @mouseleave="t.paused = false"
            class="pointer-events-auto relative w-full max-w-sm overflow-hidden rounded-xl border border-white/80 bg-white p-3.5 shadow-[0_18px_50px_rgba(15,23,42,0.24)] ring-1 ring-slate-900/5 transition-all dark:border-slate-700 dark:bg-slate-900 dark:ring-white/10"
            :class="{
                'border-emerald-500/30 dark:border-emerald-500/30 text-emerald-950 dark:text-emerald-50 shadow-emerald-500/10': t.tone === 'ok' || t.tone === 'success',
                'border-rose-500/30 dark:border-rose-500/30 text-rose-950 dark:text-rose-50 shadow-rose-500/10': t.tone === 'bad' || t.tone === 'error',
                'border-amber-500/30 dark:border-amber-500/30 text-amber-950 dark:text-amber-50 shadow-amber-500/10': t.tone === 'warn' || t.tone === 'warning',
                'border-sky-500/30 dark:border-sky-500/30 text-sky-950 dark:text-sky-50 shadow-sky-500/10': t.tone === 'info' || (!['ok','success','bad','error','warn','warning'].includes(t.tone)),
            }"
        >
                <div class="flex items-start gap-3">
                    <span class="absolute inset-y-0 left-0 w-1 rounded-l-xl"
                          :class="{
                              'bg-emerald-500': t.tone === 'ok' || t.tone === 'success',
                              'bg-rose-500': t.tone === 'bad' || t.tone === 'error',
                              'bg-amber-500': t.tone === 'warn' || t.tone === 'warning',
                              'bg-sky-500': t.tone === 'info' || (!['ok','success','bad','error','warn','warning'].includes(t.tone)),
                          }"></span>
                    {{-- Icon Badge --}}
                    <div
                    class="grid size-8 shrink-0 place-items-center rounded-full"
                    :class="{
                        'bg-emerald-500/15 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400': t.tone === 'ok' || t.tone === 'success',
                        'bg-rose-500/15 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400': t.tone === 'bad' || t.tone === 'error',
                        'bg-amber-500/15 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400': t.tone === 'warn' || t.tone === 'warning',
                        'bg-sky-500/15 text-sky-600 dark:bg-sky-500/20 dark:text-sky-400': t.tone === 'info' || (!['ok','success','bad','error','warn','warning'].includes(t.tone)),
                    }"
                >
                    {{-- Success / OK --}}
                    <template x-if="t.tone === 'ok' || t.tone === 'success'">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                    {{-- Error / Bad --}}
                    <template x-if="t.tone === 'bad' || t.tone === 'error'">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </template>
                    {{-- Warning --}}
                    <template x-if="t.tone === 'warn' || t.tone === 'warning'">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </template>
                    {{-- Info --}}
                    <template x-if="t.tone === 'info' || (!['ok','success','bad','error','warn','warning'].includes(t.tone))">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                </div>

                {{-- Content --}}
                <div class="min-w-0 flex-1 pt-0.5">
                    <template x-if="t.title">
                        <p class="font-heading text-sm font-semibold leading-snug text-slate-900 dark:text-white" x-text="t.title"></p>
                    </template>
                    <p class="mt-0.5 text-[13px] leading-snug text-slate-600 dark:text-slate-300" x-text="t.message"></p>
                </div>

                {{-- Close Button --}}
                <button
                    type="button"
                    @click="remove(t.id)"
                    class="grid size-7 shrink-0 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-slate-200"
                    aria-label="Dismiss notification"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Auto-decay progress line at the bottom --}}
            <div class="absolute inset-x-0 bottom-0 h-1 bg-black/5 dark:bg-white/5">
                <div
                    class="h-full transition-[width] duration-75 ease-linear"
                    :style="'width: ' + t.progress + '%'"
                    :class="{
                        'bg-emerald-500': t.tone === 'ok' || t.tone === 'success',
                        'bg-rose-500': t.tone === 'bad' || t.tone === 'error',
                        'bg-amber-500': t.tone === 'warn' || t.tone === 'warning',
                        'bg-sky-500': t.tone === 'info' || (!['ok','success','bad','error','warn','warning'].includes(t.tone)),
                    }"
                ></div>
            </div>
        </div>
    </template>
</div>
