<x-layouts.guest title="Forgot password">
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Forgot password</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Enter the email address for your staff account and we'll send you a password reset link.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-lg bg-emerald-50 border border-emerald-200/60 p-3.5 text-sm font-medium text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-300">
            <div class="flex items-start gap-2.5">
                <svg class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                   autocomplete="email"
                   placeholder="name@prativa.edu.np"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-slate-900 dark:border-white/10 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500" />
            @error('email')
                <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors">
            Send password reset link
        </button>

        <div class="pt-2 text-center">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-300 transition-colors">
                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                <span>Back to sign in</span>
            </a>
        </div>
    </form>
</x-layouts.guest>
