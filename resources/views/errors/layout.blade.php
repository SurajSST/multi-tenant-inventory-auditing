<!doctype html>
<html lang="en" x-data="{ theme: localStorage.getItem('prativa.theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') }"
      x-init="$el.setAttribute('data-theme', theme); $watch('theme', v => { localStorage.setItem('prativa.theme', v); $el.setAttribute('data-theme', v); })">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Error') · {{ config('prativa.school_name', 'Prativa Secondary School') }}</title>

    {{-- PWA Manifest & Meta Tags --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#090D16">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Prativa Stock">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/icon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet" />

    {{-- Theme initialization script to prevent flash of wrong theme --}}
    <script>
        (function() {
            function applyTheme() {
                const saved = localStorage.getItem('prativa.theme');
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = saved || (prefersDark ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
            }
            applyTheme();
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-slate-800 dark:text-slate-100 bg-slate-100 dark:bg-slate-950 min-h-screen">

<div class="relative flex min-h-screen flex-col justify-center overflow-hidden px-4 py-12 transition-colors duration-150">

    {{-- Top-right Theme Toggle --}}
    <div class="absolute right-4 top-4 z-20 sm:right-6 sm:top-6">
        <button type="button" @click="theme = theme === 'dark' ? 'light' : 'dark'; localStorage.setItem('prativa.theme', theme); document.documentElement.setAttribute('data-theme', theme);"
                class="grid size-9 place-items-center rounded-xl border border-slate-200 bg-white/90 text-slate-600 shadow-sm backdrop-blur transition hover:bg-slate-100 hover:text-slate-900 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                title="Toggle theme">
            <svg x-show="theme === 'dark'" class="size-4.5 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <svg x-show="theme !== 'dark'" x-cloak class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
        </button>
    </div>

    {{-- Soft radial glow --}}
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_0%,rgba(56,189,248,0.18),transparent)] dark:bg-[radial-gradient(ellipse_60%_50%_at_50%_0%,rgba(56,189,248,0.14),transparent)]"></div>

    <div class="relative mx-auto w-full max-w-lg">
        {{-- Branding Header --}}
        <div class="mb-6 text-center">
            <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="inline-block transition-transform hover:scale-[1.02]">
                <img src="/img/logo/prativalogo.png" alt="{{ config('prativa.school_name', 'Prativa Secondary School') }}"
                     class="mx-auto h-20 w-auto max-w-[240px] object-contain drop-shadow-[0_4px_16px_rgba(56,189,248,0.12)] dark:hidden" />
                <img src="/img/logo/prativaLogoWhite.png" alt="{{ config('prativa.school_name', 'Prativa Secondary School') }}"
                     class="mx-auto h-20 w-auto max-w-[240px] object-contain drop-shadow-[0_4px_16px_rgba(56,189,248,0.2)] hidden dark:block" />
            </a>
            <p class="mt-3 font-mono text-[11px] font-semibold uppercase tracking-widest text-sky-600 dark:text-sky-400">
                {{ config('prativa.system_name', 'Internal Stock Auditing & Procurement') }}
            </p>
        </div>

        {{-- Error Card --}}
        <div class="overflow-hidden rounded-2xl bg-white p-6 shadow-xl ring-1 ring-slate-900/5 sm:p-8 dark:bg-[#0F1623] dark:shadow-2xl dark:ring-white/10">
            <div class="text-center">
                {{-- Error Icon --}}
                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl @yield('icon-bg', 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400') ring-1 ring-black/5 dark:ring-white/10">
                    @yield('icon')
                </div>

                {{-- Status Code Badge --}}
                <div class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold tracking-wide @yield('badge-class', 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-300')">
                    <span class="size-1.5 rounded-full @yield('dot-class', 'bg-slate-400')"></span>
                    <span>HTTP @yield('code', '500')</span>
                </div>

                {{-- Error Heading --}}
                <h1 class="mt-3 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl dark:text-white">
                    @yield('heading', 'Something went wrong')
                </h1>

                {{-- Error Description --}}
                <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                    @yield('message', 'An error occurred while processing your request.')
                </p>

                {{-- Optional Detail Note --}}
                @hasSection('detail')
                    <div class="mt-4 rounded-xl border border-slate-200/80 bg-slate-50/80 p-3.5 text-left text-xs leading-relaxed text-slate-600 dark:border-white/10 dark:bg-white/[.03] dark:text-slate-400">
                        @yield('detail')
                    </div>
                @endif
            </div>

            {{-- Actions --}}
            <div class="mt-8 flex flex-col-reverse gap-2.5 sm:flex-row sm:items-center sm:justify-center">
                <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ auth()->check() ? route('dashboard') : url('/') }}'"
                        class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <svg class="size-4 shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Go back
                </button>

                @auth
                    <a href="{{ route('dashboard') }}"
                       class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:bg-sky-500 dark:hover:bg-sky-400">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:bg-sky-500 dark:hover:bg-sky-400">
                        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                        Sign in
                    </a>
                @endauth

                @yield('extra-actions')
            </div>
        </div>

        {{-- Footer Security Note --}}
        <p class="mt-6 text-center text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            Prativa Secondary School · Strict separation of duties is enforced.
            Need assistance? Contact your system Super Admin.
        </p>
    </div>
</div>

</body>
</html>
