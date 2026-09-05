@props(['title' => 'Sign in'])
<!doctype html>
<html lang="en" x-data="{ theme: localStorage.getItem('prativa.theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') }"
      x-init="$el.setAttribute('data-theme', theme); $watch('theme', v => { localStorage.setItem('prativa.theme', v); $el.setAttribute('data-theme', v); })">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Prativa Stock and Procurement</title>

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

    {{-- Google Fonts: Inter, Plus Jakarta Sans, JetBrains Mono --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet" />

    {{-- Set the theme attribute before first paint & on Livewire navigate --}}
    <script>
        (function() {
            function applyTheme() {
                const saved = localStorage.getItem('prativa.theme');
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const theme = saved || (prefersDark ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
            }
            applyTheme();
            document.addEventListener('livewire:navigated', applyTheme);
        })();
    </script>

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-slate-800 dark:text-slate-100">

<div class="relative flex min-h-screen flex-col justify-center overflow-hidden bg-slate-100 px-4 py-12 transition-colors duration-150 dark:bg-slate-950">

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

    <div class="relative mx-auto w-full max-w-md">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-5 flex items-center justify-center">
                {{-- Light mode logo --}}
                <img src="/img/logo/prativalogo.png" alt="{{ config('prativa.school_name', 'Prativa Secondary School') }}" class="h-24 w-auto max-w-[280px] object-contain drop-shadow-[0_4px_16px_rgba(56,189,248,0.12)] dark:hidden" />
                {{-- Dark mode logo --}}
                <img src="/img/logo/prativaLogoWhite.png" alt="{{ config('prativa.school_name', 'Prativa Secondary School') }}" class="h-24 w-auto max-w-[280px] object-contain drop-shadow-[0_4px_16px_rgba(56,189,248,0.2)] hidden dark:block" />
            </div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 font-heading dark:text-white">{{ config('prativa.school_name', 'Prativa Secondary School') }}</h1>
            <p class="mt-1 font-mono text-[11px] font-semibold uppercase tracking-widest text-sky-600 dark:text-sky-400">{{ config('prativa.system_name', 'Stock & Procurement') }}</p>
        </div>

        <div class="rounded-2xl bg-white p-7 shadow-xl ring-1 ring-slate-900/5 dark:bg-[#0F1623] dark:shadow-2xl dark:ring-white/10">
            {{ $slot }}
        </div>

        <p class="relative mt-6 text-center text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            Internal system. Every action is recorded against the account that performed it,
            so accounts are never shared.
        </p>
    </div>
</div>

<x-toast />

@livewireScripts
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        });
    }
</script>

</body>
</html>
