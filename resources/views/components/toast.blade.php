@props([
    'sessionStatus' => session('status'),
    'sessionSuccess' => session('success'),
    'sessionError' => session('error'),
    'sessionWarning' => session('warning'),
    'sessionToast' => session('toast'),
])

<div
    x-data="{
        toasts: [],
        add(message, tone = 'ok', title = null, duration = 4000) {
            const id = Date.now() + Math.random();
            const toast = {
                id,
                message: typeof message === 'object' ? message.message || message.text || JSON.stringify(message) : message,
                tone: (typeof message === 'object' ? message.tone || message.type : tone) || 'ok',
                title: typeof message === 'object' ? message.title : title,
                duration: (typeof message === 'object' ? message.duration : duration) || 4000,
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
                this.add(msg, tone, title, duration);
            };

            window.addEventListener('toast', (e) => {
                if (e.detail) {
                    this.add(e.detail.message || e.detail.text || e.detail, e.detail.tone || e.detail.type || 'ok', e.detail.title, e.detail.duration);
                }
            });

            // Handle Livewire dispatches
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

            // Seed initial session flash toasts if present
            @if ($sessionToast)
                this.add({!! json_encode($sessionToast) !!});
            @elseif ($sessionSuccess)
                this.add(@js($sessionSuccess), 'ok', 'Success');
            @elseif ($sessionError)
                this.add(@js($sessionError), 'bad', 'Error');
            @elseif ($sessionWarning)
                this.add(@js($sessionWarning), 'warn', 'Attention');
            @elseif ($sessionStatus)
                this.add(@js($sessionStatus), 'info', 'Status Update');
            @endif
        }
    }"
    class="pointer-events-none fixed inset-x-4 top-4 z-[9999] flex flex-col items-center gap-2.5 sm:inset-x-auto sm:right-6 sm:top-6 sm:items-end no-print"
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
            class="pointer-events-auto relative w-full sm:w-[380px] overflow-hidden rounded-2xl border bg-white/95 p-4 shadow-[0_12px_36px_rgba(0,0,0,0.18)] backdrop-blur-md transition-all dark:bg-[#0D1424]/95"
            :class="{
                'border-emerald-500/30 dark:border-emerald-500/30 text-emerald-950 dark:text-emerald-50 shadow-emerald-500/10': t.tone === 'ok' || t.tone === 'success',
                'border-rose-500/30 dark:border-rose-500/30 text-rose-950 dark:text-rose-50 shadow-rose-500/10': t.tone === 'bad' || t.tone === 'error',
                'border-amber-500/30 dark:border-amber-500/30 text-amber-950 dark:text-amber-50 shadow-amber-500/10': t.tone === 'warn' || t.tone === 'warning',
                'border-sky-500/30 dark:border-sky-500/30 text-sky-950 dark:text-sky-50 shadow-sky-500/10': t.tone === 'info' || (!['ok','success','bad','error','warn','warning'].includes(t.tone)),
            }"
        >
            <div class="flex items-start gap-3.5">
                {{-- Icon Badge --}}
                <div
                    class="grid size-9 shrink-0 place-items-center rounded-xl shadow-inner"
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
                        <p class="font-heading text-[13.5px] font-bold leading-snug text-slate-900 dark:text-white" x-text="t.title"></p>
                    </template>
                    <p class="text-[12.5px] leading-relaxed text-slate-600 dark:text-slate-300" x-text="t.message"></p>
                </div>

                {{-- Close Button --}}
                <button
                    type="button"
                    @click="remove(t.id)"
                    class="grid size-7 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/10 dark:hover:text-slate-200 transition"
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
