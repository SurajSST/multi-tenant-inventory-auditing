@props(['title' => 'Sign in'])
<!doctype html>
<html lang="en" data-theme="light">
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

    <script>
        document.documentElement.setAttribute('data-theme',
            localStorage.getItem('prativa.theme') ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
    </script>

    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased">

<div class="relative flex min-h-screen flex-col justify-center overflow-hidden bg-slate-950 px-4 py-12">

    {{-- Soft radial glow, matching the reference frontend's fintech-calm sign-in screens. --}}
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_0%,rgba(56,189,248,0.14),transparent)]"></div>

    <div class="relative mx-auto w-full max-w-md">
        <div class="mb-8 text-center">
            <div class="mx-auto mb-5 flex items-center justify-center">
                <img src="/img/logo/prativaLogoWhite.png" alt="{{ config('prativa.school_name', 'Prativa Secondary School') }}" class="h-24 w-auto max-w-[280px] object-contain drop-shadow-[0_4px_16px_rgba(56,189,248,0.2)]" />
            </div>
            <h1 class="text-xl font-bold tracking-tight text-white font-heading">{{ config('prativa.school_name', 'Prativa Secondary School') }}</h1>
            <p class="mt-1 font-mono text-[11px] font-semibold uppercase tracking-widest text-sky-400">{{ config('prativa.system_name', 'Stock & Procurement') }}</p>
        </div>

        <div class="rounded-2xl bg-white p-7 shadow-2xl ring-1 ring-white/10 dark:bg-[#0F1623] dark:ring-white/10">
            {{ $slot }}
        </div>

        <p class="relative mt-6 text-center text-xs leading-relaxed text-slate-500">
            Internal system. Every action is recorded against the account that performed it,
            so accounts are never shared.
        </p>
    </div>
</div>

<x-toast />

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        });
    }
</script>

</body>
</html>
