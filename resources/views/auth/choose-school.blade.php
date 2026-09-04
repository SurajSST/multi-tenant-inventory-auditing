<x-layouts.guest title="Choose a school">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Which school?</h2>
    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
        You hold a posting at more than one. Everything you do is recorded against the school you pick,
        and you can move between them at any time.
    </p>

    <x-errors class="mt-5" />

    <form method="POST" action="{{ route('tenant.switch') }}" class="mt-6 space-y-3">
        @csrf

        @foreach ($memberships as $membership)
            <button type="submit" name="tenant_id" value="{{ $membership->tenant_id }}"
                    class="group flex w-full items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-left transition-colors hover:border-indigo-400 hover:bg-indigo-50/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-white/10 dark:bg-white/5 dark:hover:border-sky-400/50 dark:hover:bg-white/10">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="grid size-10 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white p-1 shadow-sm overflow-hidden dark:border-white/10 dark:bg-slate-900">
                        @if ($membership->tenant->logo_url)
                            <img src="{{ $membership->tenant->logo_url }}" alt="{{ $membership->tenant->name }}" class="size-full object-contain" />
                        @else
                            <span class="font-heading font-bold text-xs text-slate-500 dark:text-slate-400">
                                {{ mb_substr($membership->tenant->name, 0, 2) }}
                            </span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ $membership->tenant->name }}
                        </span>
                        <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ $membership->designation }} · {{ $membership->staff_code }}
                        </span>
                    </div>
                </div>

                <svg class="size-4 shrink-0 text-slate-300 transition-colors group-hover:text-indigo-500 dark:text-slate-600 dark:group-hover:text-sky-400"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        @endforeach
    </form>

    @if (auth()->user()?->isPlatformOwner())
        <a href="{{ route('platform.schools') }}"
           class="mt-4 block text-center text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400">
            Or open the platform console
        </a>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="text-xs font-medium text-slate-500 hover:text-slate-900 dark:text-slate-500 dark:hover:text-slate-300">
            Sign out instead
        </button>
    </form>
</x-layouts.guest>
