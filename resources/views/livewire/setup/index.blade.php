@php
    $groups = [
        'Stock register' => [
            ['Blocks & locations', 'setup.locations', $counts['locations'].' active', 'Set up the places where stock is counted and stored.'],
            ['Categories', 'setup.categories', $counts['categories'].' active', 'Organize item types and maintain their subcategories.'],
            ['Item types', 'setup.items', $counts['items'].' active', 'Manage names, code prefixes, units and reorder levels.'],
        ],
        'Approval & access' => [
            ['Approval ladder', 'setup.ladder', $counts['tiers'].' bands', 'Set approval limits and who can authorize each band.'],
            ['Staff & roles', 'setup.staff', $counts['staff'].' active', 'Create accounts and manage school-specific roles and access.'],
        ],
        'School settings' => [
            ['School settings', 'setup.settings', null, 'Set the school name, petty cash ceiling and order policy.'],
            ['Mail & push notifications', 'setup.notifications', null, 'Configure SMTP delivery and browser push notifications.'],
        ],
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-8">
    <x-page-header title="Settings & Roles"
                   subtitle="Manage this school’s register, approval rules, staff access and notification delivery." />

    @foreach ($groups as $group => $sections)
        <section aria-labelledby="setup-{{ \Illuminate\Support\Str::slug($group) }}">
            <div class="mb-3 flex items-center gap-3">
                <h2 id="setup-{{ \Illuminate\Support\Str::slug($group) }}" class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">{{ $group }}</h2>
                <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($sections as [$title, $route, $count, $description])
                    <a href="{{ route($route) }}" wire:navigate
                       class="group flex min-h-36 flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 transition hover:-translate-y-0.5 hover:shadow-md hover:ring-indigo-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 dark:bg-slate-900 dark:ring-white/10 dark:hover:ring-sky-500/40">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-sm font-semibold text-slate-900 transition group-hover:text-indigo-700 dark:text-slate-100 dark:group-hover:text-sky-300">{{ $title }}</h3>
                            @if ($count)
                                <x-badge>{{ $count }}</x-badge>
                            @endif
                        </div>
                        <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $description }}</p>
                        <span class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 dark:text-sky-400">
                            Manage
                            <svg class="size-3.5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
