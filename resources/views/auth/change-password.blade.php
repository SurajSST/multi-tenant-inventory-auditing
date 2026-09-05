<x-layouts.guest title="Change your password">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Choose a new password</h2>

    @if (auth()->user()->must_reset_password)
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            Your account still uses the password it was created with. Set your own before going any further —
            nothing in this system should be done under a password somebody else knows.
        </p>
    @else
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Changing your password signs you out of every other device.</p>
    @endif

    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('PUT')

        <input type="hidden" name="email" value="{{ auth()->user()->email }}" autocomplete="username" />

        <div x-data="{ show: false }">
            <label for="current_password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Current password</label>
            <div class="relative mt-1.5">
                <input id="current_password" name="current_password" type="password" :type="show ? 'text' : 'password'" required autofocus
                       autocomplete="current-password"
                       class="block w-full rounded-lg border-slate-300 pr-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-slate-900 dark:border-white/10 dark:text-white" />
                <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors focus:outline-none"
                        tabindex="-1" :aria-label="show ? 'Hide password' : 'Show password'" :title="show ? 'Hide password' : 'Show password'">
                    <svg x-show="!show" class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="show" x-cloak class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                </button>
            </div>
            @error('current_password') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div x-data="{ show: false }">
            <label for="password" class="block text-sm font-medium text-slate-700 dark:text-slate-300">New password</label>
            <div class="relative mt-1.5">
                <input id="password" name="password" type="password" :type="show ? 'text' : 'password'" required autocomplete="new-password"
                       class="block w-full rounded-lg border-slate-300 pr-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-slate-900 dark:border-white/10 dark:text-white" />
                <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors focus:outline-none"
                        tabindex="-1" :aria-label="show ? 'Hide password' : 'Show password'" :title="show ? 'Hide password' : 'Show password'">
                    <svg x-show="!show" class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="show" x-cloak class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                </button>
            </div>
            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">At least 10 characters.</p>
            @error('password') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div x-data="{ show: false }">
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Confirm new password</label>
            <div class="relative mt-1.5">
                <input id="password_confirmation" name="password_confirmation" type="password" :type="show ? 'text' : 'password'" required
                       autocomplete="new-password"
                       class="block w-full rounded-lg border-slate-300 pr-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-slate-900 dark:border-white/10 dark:text-white" />
                <button type="button" @click="show = !show"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors focus:outline-none"
                        tabindex="-1" :aria-label="show ? 'Hide password' : 'Show password'" :title="show ? 'Hide password' : 'Show password'">
                    <svg x-show="!show" class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="show" x-cloak class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            Change password
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button class="w-full text-center text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">Sign out instead</button>
    </form>
</x-layouts.guest>
