@php
    $user = auth()->user();

    // Which school this is, and whether this person works at more than one.
    // Somebody posted to a single school sees none of the tenant furniture —
    // the app looks exactly as it did before there was more than one.
    $currentSchool = app(\App\Tenancy\TenantContext::class)->current();
    $otherSchools = $user
        ? $user->activeMemberships()->reject(fn ($m) => $m->tenant_id === $currentSchool?->id)
        : collect();

    // Calculate pending approvals count for the badge (only when school context is active)
    $pendingApprovalsCount = 0;
    if ($currentSchool && $user && app(\App\Tenancy\TenantContext::class)->has()) {
        try {
            if ($user->approval_tier > 0) {
                $pendingApprovalsCount = \App\Models\DemandForm::where('status', \App\Enums\DemandStatus::PENDING)
                    ->where('current_tier', $user->approval_tier)
                    ->where('raised_by_id', '!=', $user->id)
                    ->count();
            }
        } catch (\Throwable $e) {
            $pendingApprovalsCount = 0;
        }
    }

    // Navigation groups matching reference/frontend
    if (! $currentSchool) {
        // Platform Mode (no school chosen): Show only platform console navigation
        $navGroups = collect([
            'Platform' => [
                ['Schools & Instances', 'platform.schools', 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6', $user?->isPlatformOwner() ?? false, 0, 'Schools'],
            ],
        ])->map(fn ($items) => collect($items)->filter(fn ($i) => $i[3])->values())->filter(fn ($items) => $items->isNotEmpty());
    } else {
        $navGroups = collect([
            'Register' => [
                ['Dashboard', 'dashboard', 'M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z', true, 0, 'Home'],
                ['Stock Register', 'inventory.register', 'M2.97 12.92A2 2 0 0 0 2 14.63v3.24a2 2 0 0 0 .97 1.71l3 1.8a2 2 0 0 0 2.06 0L12 19v-5.5l-5-3-4.03 2.42ZM7 16.5l-4.74-2.85M7 16.5l5-3M7 16.5v5.17M12 13.5V19l3.97 2.38a2 2 0 0 0 2.06 0l3-1.8a2 2 0 0 0 .97-1.71v-3.24a2 2 0 0 0-.97-1.71L17 10.5l-5 3ZM17 16.5l-5-3M17 16.5l4.74-2.85M17 16.5v5.17M7.97 4.42A2 2 0 0 0 7 6.13v4.37l5 3 5-3V6.13a2 2 0 0 0-.97-1.71l-3-1.8a2 2 0 0 0-2.06 0l-3 1.8ZM12 8l-4.74-2.85M12 8l4.74-2.85M12 13.5V8', true, 0, 'Stock'],
                ['Audit Count', 'inventory.count', 'M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M8 2h8a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1ZM9 14l2 2 4-4', $user?->can('enter-counts'), 0, 'Count'],
                ['Variance', 'inventory.variance', 'M22 17l-8.5-8.5-5 5L2 7M16 17h6v-6', true, 0, 'Variance'],
            ],
            'Procurement' => [
                ['Demand Forms', 'demands.index', 'M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7ZM14 2v4a2 2 0 0 0 2 2h4M10 9H8M16 13H8M16 17H8', true, 0, 'Demands'],
                ['Approvals Queue', 'demands.queue', 'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10ZM9 12l2 2 4-4', $user?->approval_tier > 0, $pendingApprovalsCount, 'Approve'],
                ['Orders & Receipts', 'orders.index', 'M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2M15 18H9M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14M17 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z', true, 0, 'Orders'],
                ['Bills & 3-Way Match', 'bills.index', 'M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1ZM16 8H8M16 12H8M13 16H8', $user?->can('handle-accounts'), 0, 'Bills'],
            ],
            'Treasury' => [
                ['Petty Cash Tokens', 'petty-cash.index', 'M8 14a6 6 0 1 0 0-12 6 6 0 0 0 0 12ZM18.09 10.37A6 6 0 1 1 10.34 18M7 6h1v4M16.71 13.88l.7.71-2.82 2.82', $user?->can('handle-accounts'), 0, 'Tokens'],
            ],
            'Governance' => [
                ['Settings & Roles', 'setup.index', 'M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z', $user?->can('manage-setup'), 0, 'Setup'],
                ['Audit Trail', 'audit.index', 'M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8M3 3v5h5M12 7v5l4 2', $user?->can('view-audit-trail'), 0, 'Trail'],
            ],
            'Platform' => [
                ['Schools', 'platform.schools', 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6', $user?->isPlatformOwner() ?? false, 0, 'Schools'],
            ],
        ])->map(fn ($items) => collect($items)->filter(fn ($i) => $i[3])->values())->filter(fn ($items) => $items->isNotEmpty());
    }

    // Flat list for command palette
    $paletteItems = $navGroups->flatten(1)->map(fn ($i) => ['label' => $i[0], 'url' => route($i[1])])->values();

    // Mobile primary bottom tabs
    $mobileTabs = collect([
        ['Dashboard', 'dashboard', 'M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z', 'Home', 0],
        ['Stock Register', 'inventory.register', 'M2.97 12.92A2 2 0 0 0 2 14.63v3.24a2 2 0 0 0 .97 1.71l3 1.8a2 2 0 0 0 2.06 0L12 19v-5.5l-5-3-4.03 2.42ZM7 16.5l-4.74-2.85M7 16.5l5-3M7 16.5v5.17M12 13.5V19l3.97 2.38a2 2 0 0 0 2.06 0l3-1.8a2 2 0 0 0 .97-1.71v-3.24a2 2 0 0 0-.97-1.71L17 10.5l-5 3ZM17 16.5l-5-3M17 16.5l4.74-2.85M17 16.5v5.17M7.97 4.42A2 2 0 0 0 7 6.13v4.37l5 3 5-3V6.13a2 2 0 0 0-.97-1.71l-3-1.8a2 2 0 0 0-2.06 0l-3 1.8ZM12 8l-4.74-2.85M12 8l4.74-2.85M12 13.5V8', 'Stock', 0],
        ['Audit Count', 'inventory.count', 'M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M8 2h8a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1ZM9 14l2 2 4-4', 'Count', 0, $user?->can('enter-counts')],
        ['Demand Forms', 'demands.index', 'M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7ZM14 2v4a2 2 0 0 0 2 2h4M10 9H8M16 13H8M16 17H8', 'Demands', 0],
    ])->filter(fn ($t) => !isset($t[5]) || $t[5])->values();
@endphp
<!doctype html>
<html lang="en" x-data="{ theme: localStorage.getItem('prativa.theme') ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') }"
      x-init="$watch('theme', v => { localStorage.setItem('prativa.theme', v); $el.setAttribute('data-theme', v); })"
      data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Prativa Stock and Procurement' }}</title>

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

    {{-- Set the theme attribute before first paint --}}
    <script>
        document.documentElement.setAttribute('data-theme',
            localStorage.getItem('prativa.theme') ?? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F8FAFC] font-sans text-[#0F172A] antialiased dark:bg-[#080C14] dark:text-[#F8FAFC]">

<div class="min-h-screen lg:flex" x-data="{ menu: false, cmdk: false }" @keydown.window="if (($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'k') { $event.preventDefault(); cmdk = true; }">

    {{-- Mobile Top Bar (Clean PWA Topbar) --}}
    <header class="sticky top-0 z-30 flex items-center justify-between gap-3 bg-white/90 px-4 py-3 backdrop-blur border-b border-slate-200/80 no-print lg:hidden dark:bg-[#090D16]/90 dark:border-white/[0.06]">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="grid size-8 shrink-0 place-items-center rounded-lg bg-white/10 p-0.5 border border-slate-200/50 dark:border-white/10 shadow-sm overflow-hidden">
                @if ($currentSchool?->logo_url)
                    <img src="{{ $currentSchool->logo_url }}" alt="{{ $currentSchool->name }}" class="size-full object-contain" />
                @elseif ($currentSchool)
                    <img src="/img/logo/prativalogo.png" alt="{{ $currentSchool->name }}" class="size-full object-contain dark:hidden" />
                    <img src="/img/logo/prativaLogoWhite.png" alt="{{ $currentSchool->name }}" class="size-full object-contain hidden dark:block" />
                @else
                    <span class="font-mono text-xs font-bold text-sky-500">SYS</span>
                @endif
            </div>
            <div class="min-w-0">
                <span class="block truncate font-heading text-sm font-bold tracking-tight text-slate-900 dark:text-white">
                    {{ $currentSchool?->name ?? 'Platform Console' }}
                </span>
                <span class="block font-mono text-[9px] uppercase tracking-wider text-sky-500 font-semibold">
                    {{ $currentSchool ? 'Stock & Procurement' : 'System Administration' }}
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @if ($user?->isPlatformOwner() && $currentSchool)
                <form method="POST" action="{{ route('platform.exit') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-sky-500/30 bg-sky-500/10 px-2 py-1 text-[11px] font-semibold text-sky-600 dark:text-sky-300 hover:bg-sky-500/20">
                        Exit school
                    </button>
                </form>
            @endif

            <button type="button" @click="cmdk = true" class="grid size-8 place-items-center rounded-lg border border-slate-200 bg-slate-50 text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-300" aria-label="Quick Search">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5A6.5 6.5 0 114 10.5a6.5 6.5 0 0113 0z" />
                </svg>
            </button>

            @auth
                <livewire:notification-bell />
            @endauth

            <button type="button" @click="theme = theme === 'dark' ? 'light' : 'dark'" class="grid size-8 place-items-center rounded-lg border border-slate-200 bg-slate-50 text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-300" aria-label="Toggle theme">
                <svg x-show="theme === 'dark'" class="size-4 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <svg x-show="theme !== 'dark'" x-cloak class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
            </button>

            <button type="button" @click="menu = true" class="grid size-8 place-items-center rounded-lg bg-gradient-to-br from-[#2563EB] to-[#0284C7] font-mono text-[11px] font-bold text-white shadow-sm" aria-label="Menu">
                {{ collect(explode(' ', $user?->full_name ?? ''))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
            </button>
        </div>
    </header>

    {{-- Desktop Sidebar (Responsive to Light/Dark Mode) --}}
    <aside
        class="hidden lg:flex w-[260px] min-w-[260px] shrink-0 flex-col border-r border-slate-200/80 bg-white text-slate-600 sticky top-0 h-screen no-print transition-colors duration-150 dark:border-white/[0.06] dark:bg-[#090D16] dark:text-[#94A3B8]"
    >
        {{-- Workspace Brand Box --}}
        <div class="flex items-center justify-center border-b border-slate-200/80 px-5 py-4.5 dark:border-white/[0.06]">
            @if ($currentSchool?->logo_url)
                <img src="{{ $currentSchool->logo_url }}" alt="{{ $currentSchool->name }}" class="h-10 w-auto max-w-full object-contain" />
            @elseif ($currentSchool)
                <img src="/img/logo/prativalogo.png" alt="{{ $currentSchool->name }}" class="h-10 w-auto max-w-full object-contain dark:hidden" />
                <img src="/img/logo/prativaLogoWhite.png" alt="{{ $currentSchool->name }}" class="h-10 w-auto max-w-full object-contain hidden dark:block" />
            @else
                <div class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-lg bg-sky-500/15 text-sky-600 font-bold font-mono text-sm border border-sky-500/25 dark:bg-sky-500/20 dark:text-sky-400 dark:border-sky-500/30">P</span>
                    <div>
                        <span class="block font-heading text-sm font-bold tracking-tight text-slate-900 dark:text-white">Platform Console</span>
                        <span class="block font-mono text-[9px] uppercase tracking-wider text-sky-600 dark:text-sky-400">System Admin</span>
                    </div>
                </div>
            @endif
        </div>

        @if ($user?->isPlatformOwner() && $currentSchool)
            <div class="px-3 pt-3">
                <form method="POST" action="{{ route('platform.exit') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-between gap-2 rounded-lg border border-sky-500/25 bg-sky-50 px-3 py-2 text-left text-xs font-semibold text-sky-700 hover:bg-sky-100 hover:text-sky-900 transition-all dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-400 dark:hover:bg-sky-500/20 dark:hover:text-white">
                        <span class="flex items-center gap-1.5 truncate">
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Exit to Console
                        </span>
                        <span class="rounded bg-sky-500/15 px-1.5 py-0.5 font-mono text-[9px] text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">Global</span>
                    </button>
                </form>
            </div>
        @endif

        {{-- Global Quick Search (Ctrl+K) --}}
        <div class="px-3 pt-3 pb-1">
            <button type="button" @click="cmdk = true"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-left text-slate-500 transition-all hover:border-slate-300 hover:bg-slate-100 hover:text-slate-800 dark:border-white/[0.08] dark:bg-white/[0.05] dark:text-[#94A3B8] dark:hover:border-white/[0.14] dark:hover:bg-white/[0.09] dark:hover:text-[#E2E8F0]">
                <span class="flex items-center gap-2 text-[12.5px] font-medium">
                    <svg class="size-3.5 text-slate-400 dark:text-[#94A3B8]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5A6.5 6.5 0 114 10.5a6.5 6.5 0 0113 0z" />
                    </svg>
                    Quick search...
                </span>
                <kbd class="rounded border border-slate-300 bg-white px-1.5 py-0.5 font-mono text-[10px] text-slate-600 shadow-2xs dark:border-white/10 dark:bg-white/10 dark:text-[#E2E8F0]">Ctrl+K</kbd>
            </button>
        </div>

        {{-- Navigation Groups --}}
        <nav class="scroll-thin flex-1 space-y-4 overflow-y-auto px-2.5 py-3">
            @foreach ($navGroups as $group => $items)
                <div>
                    <p class="px-3 pb-1.5 pt-1 font-mono text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400 dark:text-[#64748B]">{{ $group }}</p>
                    <div class="space-y-0.5">
                        @foreach ($items as [$label, $route, $icon, $allowed, $badgeCount])
                            @php
                                $isActive = match($route) {
                                    'dashboard' => request()->routeIs('dashboard'),
                                    'inventory.register' => request()->routeIs('inventory.register*') || (request()->routeIs('inventory.*') && !request()->routeIs('inventory.count*') && !request()->routeIs('inventory.variance*')),
                                    'inventory.count' => request()->routeIs('inventory.count*'),
                                    'inventory.variance' => request()->routeIs('inventory.variance*'),
                                    'demands.queue' => request()->routeIs('demands.queue*'),
                                    'demands.index' => (request()->routeIs('demands.*') && !request()->routeIs('demands.queue*')),
                                    'orders.index' => request()->routeIs('orders.*'),
                                    'bills.index' => request()->routeIs('bills.*'),
                                    'petty-cash.index' => request()->routeIs('petty-cash.*'),
                                    'setup.index' => request()->routeIs('setup.*'),
                                    'audit.index' => request()->routeIs('audit.*'),
                                    default => request()->routeIs($route),
                                };
                            @endphp
                            <a href="{{ route($route) }}" wire:navigate
                               class="group flex items-center gap-2.5 rounded-lg px-3 py-[8.5px] text-[13.5px] font-medium transition-all duration-150 mb-0.5
                                      {{ $isActive
                                          ? 'bg-sky-50 text-sky-700 font-semibold shadow-2xs dark:bg-[#38BDF8]/[0.14] dark:text-[#38BDF8]'
                                          : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-[#94A3B8] dark:hover:bg-white/[0.06] dark:hover:text-white' }}">
                                <svg class="size-[17px] shrink-0 {{ $isActive ? 'text-sky-600 dark:text-[#38BDF8]' : 'text-slate-400 group-hover:text-slate-700 dark:text-[#94A3B8] dark:group-hover:text-white' }}"
                                     fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                                </svg>
                                <span class="truncate">{{ $label }}</span>

                                @if ($badgeCount > 0)
                                    <span class="ml-auto flex items-center justify-center rounded-full bg-[#EF4444] px-1.5 py-0.5 font-mono text-[10px] font-semibold text-white">
                                        {{ $badgeCount }}
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- Footer Profile & Theme Toggle --}}
        <div class="border-t border-slate-200/80 p-4 dark:border-white/[0.06]">
            @if ($currentSchool)
                <div class="mb-3" @if ($otherSchools->isNotEmpty()) x-data="{ schools: false }" @endif>
                    <div class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-2 dark:border-white/[0.08] dark:bg-white/[0.04]">
                        <span class="grid size-6 shrink-0 place-items-center rounded bg-slate-200 font-mono text-[10px] font-bold text-slate-700 dark:bg-white/10 dark:text-white">
                            {{ strtoupper(substr($currentSchool->short_name ?: $currentSchool->name, 0, 2)) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[12px] font-semibold text-slate-800 dark:text-white">{{ $currentSchool->name }}</span>
                        </span>
                        @if ($otherSchools->isNotEmpty())
                            <button type="button" @click="schools = !schools"
                                    class="shrink-0 rounded p-1 text-slate-500 transition hover:bg-slate-200 hover:text-slate-900 dark:text-[#94A3B8] dark:hover:bg-white/10 dark:hover:text-white"
                                    aria-label="Switch school">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M16 15l-4 4-4-4" />
                                </svg>
                            </button>
                        @endif
                    </div>

                    @if ($otherSchools->isNotEmpty())
                        <form method="POST" action="{{ route('tenant.switch') }}" x-show="schools" x-cloak class="mt-1.5 space-y-1">
                            @csrf
                            @foreach ($otherSchools as $membership)
                                <button type="submit" name="tenant_id" value="{{ $membership->tenant_id }}"
                                        class="block w-full truncate rounded-md px-2.5 py-1.5 text-left text-[12px] text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-[#94A3B8] dark:hover:bg-white/[0.06] dark:hover:text-white">
                                    {{ $membership->tenant->name }}
                                    <span class="block truncate text-[10.5px] text-slate-400 dark:text-[#64748B]">{{ $membership->designation }}</span>
                                </button>
                            @endforeach
                        </form>
                    @endif
                </div>
            @endif

            <div class="mb-3 flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <p class="truncate text-[13px] font-semibold text-slate-900 dark:text-white">{{ $user?->full_name }}</p>
                    <p class="truncate text-[11px] text-slate-500 dark:text-[#94A3B8]">{{ $user?->designation }}</p>
                </div>
                <button type="button" @click="theme = theme === 'dark' ? 'light' : 'dark'"
                        class="grid size-8 shrink-0 place-items-center rounded-lg border border-slate-200 bg-slate-50 text-slate-600 transition hover:bg-slate-100 dark:border-white/10 dark:bg-white/5 dark:text-slate-300 dark:hover:bg-white/10"
                        :aria-label="theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'"
                        title="Toggle theme">
                    <svg x-show="theme === 'dark'" class="size-4 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <svg x-show="theme !== 'dark'" x-cloak class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="flex w-full items-center justify-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-[12.5px] font-semibold text-slate-700 transition-all hover:bg-slate-100 hover:text-slate-900 hover:border-slate-300 dark:border-white/[0.12] dark:bg-white/[0.06] dark:text-white dark:hover:bg-white/10 dark:hover:border-white/20">
                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                    </svg>
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    {{-- Content --}}
    <div class="min-w-0 flex-1 flex flex-col pb-[calc(64px+env(safe-area-inset-bottom,0px))] lg:pb-0">
        {{-- Desktop Top Bar --}}
        <header class="sticky top-0 z-20 hidden items-center gap-3 border-b border-slate-200/80 bg-white/88 px-8 py-3.5 backdrop-blur no-print lg:flex dark:border-white/[0.06] dark:bg-[#0F1623]/88">
            <div class="ml-auto flex items-center gap-2.5">
                <button type="button" @click="cmdk = true"
                        class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-[12.5px] font-medium text-slate-500 transition hover:border-slate-300 hover:text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-400 dark:hover:bg-white/10">
                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5A6.5 6.5 0 114 10.5a6.5 6.5 0 0113 0z" />
                    </svg>
                    Search
                    <kbd class="rounded border border-slate-300 bg-white px-1 font-mono text-[10px] text-slate-400 dark:border-white/10 dark:bg-white/10 dark:text-slate-500">Ctrl K</kbd>
                </button>

                @auth
                    <livewire:notification-bell />
                @endauth

                <div class="grid size-8 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-[#2563EB] to-[#0284C7] font-mono text-[11px] font-bold text-white shadow-sm border border-white/15">
                    {{ collect(explode(' ', $user?->full_name ?? ''))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-[1480px] w-full px-4 py-5 sm:px-6 lg:px-9 lg:py-8 flex-1">
            @if (session('status'))
                <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 no-print dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{-- Mobile Bottom Tabbar (1:1 Native App feel like reference/frontend) --}}
    <nav class="pwa-tabbar no-print" aria-label="Main">
        @foreach ($mobileTabs as [$label, $route, $icon, $short, $badgeCount])
            @php
                $isActive = match($route) {
                    'dashboard' => request()->routeIs('dashboard'),
                    'inventory.register' => request()->routeIs('inventory.register*') || (request()->routeIs('inventory.*') && !request()->routeIs('inventory.count*') && !request()->routeIs('inventory.variance*')),
                    'inventory.count' => request()->routeIs('inventory.count*'),
                    'demands.index' => (request()->routeIs('demands.*') && !request()->routeIs('demands.queue*')),
                    default => request()->routeIs($route),
                };
            @endphp
            <a href="{{ route($route) }}" wire:navigate class="pwa-tab {{ $isActive ? 'on' : '' }}">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
                <span>{{ $short }}</span>
                @if ($badgeCount > 0)
                    <span class="dot">{{ $badgeCount }}</span>
                @endif
            </a>
        @endforeach

        <button type="button" @click="menu = true" class="pwa-tab">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <span>More</span>
        </button>
    </nav>

    {{-- Mobile Slide-up "More" Bottom Sheet --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 backdrop-blur-sm lg:hidden" @click="menu = false">
        <div class="w-full max-h-[88vh] flex flex-col rounded-t-2xl bg-white shadow-2xl overflow-hidden border-t border-slate-200 animate-sheet-rise dark:bg-[#0F1623] dark:border-white/10" @click.stop>
            {{-- Grab Bar --}}
            <div class="mx-auto mt-3 h-1 w-10 rounded-full bg-slate-300 dark:bg-slate-700"></div>

            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5 dark:border-white/10">
                <div>
                    <h3 class="font-heading text-base font-bold text-slate-900 dark:text-white">{{ $user?->full_name }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $user?->designation }}</p>
                </div>
                <button type="button" @click="menu = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10 text-lg font-bold" aria-label="Close">×</button>
            </div>

            {{-- All Nav Items --}}
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                @foreach ($navGroups as $group => $items)
                    <div>
                        <p class="px-3 pb-1.5 font-mono text-[9.5px] font-bold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500">{{ $group }}</p>
                        <div class="space-y-1">
                            @foreach ($items as [$label, $route, $icon, $allowed, $badgeCount])
                                @php
                                    $isActive = request()->routeIs($route);
                                @endphp
                                <a href="{{ route($route) }}" wire:navigate @click="menu = false"
                                   class="flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-medium transition
                                          {{ $isActive ? 'bg-sky-50 text-sky-600 font-semibold dark:bg-sky-500/15 dark:text-sky-300' : 'text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-white/5' }}">
                                    <div class="flex items-center gap-3">
                                        <svg class="size-[18px] shrink-0 {{ $isActive ? 'text-sky-500' : 'text-slate-400 dark:text-slate-500' }}" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                                        </svg>
                                        <span>{{ $label }}</span>
                                    </div>
                                    @if ($badgeCount > 0)
                                        <span class="rounded-full bg-[#EF4444] px-2 py-0.5 font-mono text-[10.5px] font-semibold text-white">{{ $badgeCount }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Bottom Footer --}}
            <div class="border-t border-slate-100 p-4 bg-slate-50 dark:bg-slate-900/50 dark:border-white/10">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex w-full items-center justify-center gap-2 rounded-xl bg-rose-50 border border-rose-200 py-2.5 px-4 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:bg-rose-500/10 dark:border-rose-500/20 dark:text-rose-300">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                        </svg>
                        Sign out of this device
                    </button>
                </form>
            </div>
        </div>
    </div>

    <x-command-palette :items="$paletteItems" />
    <x-toast />
</div>

{{-- Register PWA Service Worker --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(err => {
                console.warn('PWA registration failed:', err);
            });
        });
    }
</script>

</body>
</html>
