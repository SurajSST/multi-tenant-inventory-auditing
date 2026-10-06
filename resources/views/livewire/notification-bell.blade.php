<div class="relative" @click.outside="$wire.open && $wire.set('open', false)"
     x-data="{
        pushMessage: '',
        pushError: false,
        async push() {
            this.pushMessage = '';
            this.pushError = false;
            try {
                const vapidPublicKey = @js($pushPublicKey);
                if (!vapidPublicKey) throw new Error('Push notifications are not configured by your administrator.');
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) throw new Error('Push is not supported by this browser.');
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') throw new Error('Allow notifications in your browser settings to continue.');
                const registration = await navigator.serviceWorker.ready;
                const decodedKey = atob(vapidPublicKey.replace(/-/g, '+').replace(/_/g, '/'));
                const applicationServerKey = Uint8Array.from(decodedKey, character => character.charCodeAt(0));
                const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey });
                const response = await fetch(@js(route('push-subscriptions.store')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                    body: JSON.stringify(subscription.toJSON()),
                });
                if (!response.ok) {
                    const body = await response.json().catch(() => ({}));
                    throw new Error(body.message || 'The browser could not save this device. Please retry.');
                }
                this.pushMessage = 'Push notifications are enabled on this device.';
            } catch (error) {
                this.pushError = true;
                this.pushMessage = error.message || 'Could not enable push notifications.';
            }
        }
     }">
    <button type="button" wire:click="toggle"
            class="relative grid size-9 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white"
            aria-label="{{ $this->unread ? $this->unread.' unread notifications' : 'Notifications' }}">
        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" />
        </svg>

        @if ($this->unread > 0)
            <span class="absolute -right-0.5 -top-0.5 grid min-w-4.5 place-items-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white ring-2 ring-white dark:ring-[#0F1623]">
                {{ $this->unread > 9 ? '9+' : $this->unread }}
            </span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 z-50 mt-2 w-80 origin-top-right overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl sm:w-96 dark:border-white/10 dark:bg-[#141C2B]">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5 dark:border-white/10">
                <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    Waiting for you
                </span>
                @if ($this->unread > 0)
                    <button type="button" wire:click="markAllRead"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400">
                        Mark all read
                    </button>
                @endif
            </div>

            @if ($pushPublicKey)
                <div class="border-b border-slate-100 px-4 py-2 dark:border-white/10">
                    <button type="button" @click="push()" class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400">Enable push on this device</button>
                    <p x-cloak x-show="pushMessage" x-text="pushMessage" role="status" class="mt-1 text-xs" :class="pushError ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300'"></p>
                </div>
            @endif

            <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto dark:divide-white/5">
                @forelse ($this->items as $item)
                    @php($data = $item->data)
                    <a href="{{ $data['action_url'] ?? '#' }}" wire:navigate
                       wire:click="markRead('{{ $item->id }}')"
                       class="flex gap-3 px-4 py-3 transition hover:bg-slate-50 dark:hover:bg-white/5 {{ $item->read_at ? '' : 'bg-indigo-50/40 dark:bg-sky-500/5' }}">
                        <span @class([
                            'mt-1.5 size-2 shrink-0 rounded-full',
                            'bg-indigo-500 dark:bg-sky-400' => ! $item->read_at,
                            'bg-transparent' => $item->read_at,
                        ])></span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-medium leading-snug text-slate-900 dark:text-slate-100">
                                {{ $data['headline'] ?? 'Something needs your attention' }}
                            </span>

                            @foreach (array_slice($data['details'] ?? [], 0, 2) as $line)
                                <span class="mt-0.5 block text-xs leading-snug text-slate-500 dark:text-slate-400">{{ $line }}</span>
                            @endforeach

                            <span class="mt-1 block text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $item->created_at->diffForHumans() }}
                            </span>
                        </span>
                    </a>
                @empty
                    <div class="px-4 py-10 text-center">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Nothing is waiting for you.</p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                            You will be told here when a form reaches your band, or when something you
                            raised is decided.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    @endif
</div>
