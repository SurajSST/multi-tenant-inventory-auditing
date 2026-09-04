@php
    $sections = [
        ['Blocks and Locations', 'setup.locations', $counts['locations'].' active', 'Where stock physically sits. Each block carries its own code prefix.'],
        ['Categories', 'setup.categories', $counts['categories'].' active', 'Categories and their subcategories, as the register groups things.'],
        ['Item Types', 'setup.items', $counts['items'].' active', 'The code prefixes themselves — CHAIR.S, LAPTOP, 2024.3T. Unique across the school.'],
        ['Approval Ladder', 'setup.ladder', $counts['tiers'].' bands', 'Which rupee value needs whose signature. Bands cannot overlap or leave a gap.'],
        ['Staff', 'setup.staff', $counts['staff'].' active', 'Accounts, roles, approval tiers and auditor block assignments.'],
        ['Settings', 'setup.settings', null, 'School name and the petty cash ceiling.'],
    ];
@endphp

<div>
    <x-page-header title="Setup"
                   subtitle="Everything the school configures for itself. Changes here are recorded in the audit trail like any other action." />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($sections as [$title, $route, $count, $description])
            <a href="{{ route($route) }}" wire:navigate
               class="group rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 transition hover:ring-indigo-300 dark:bg-slate-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-sm font-semibold text-slate-900 group-hover:text-indigo-600 dark:text-slate-100">{{ $title }}</h2>
                    @if ($count)
                        <x-badge>{{ $count }}</x-badge>
                    @endif
                </div>
                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-500">{{ $description }}</p>
            </a>
        @endforeach
    </div>

    <x-card class="mt-6" title="Before this handles real money">
        <ul class="space-y-2.5 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            <li class="flex gap-2.5">
                <span class="text-slate-400 dark:text-slate-600">1.</span>
                <span><strong class="text-slate-900 dark:text-slate-100">Backups.</strong> A nightly <code class="rounded bg-slate-100 px-1 text-xs dark:bg-white/10">mysqldump</code> copied off the machine. An audit system with no backup is not an audit system.</span>
            </li>
            <li class="flex gap-2.5">
                <span class="text-slate-400 dark:text-slate-600">2.</span>
                <span><strong class="text-slate-900 dark:text-slate-100">TLS.</strong> Put nginx or Caddy in front. Never run this over plain HTTP, even inside the school network.</span>
            </li>
            <li class="flex gap-2.5">
                <span class="text-slate-400 dark:text-slate-600">3.</span>
                <span><strong class="text-slate-900 dark:text-slate-100">Real opening balances.</strong> The stock figures loaded at setup are sample data. Replace them with the auditor's actual count, or the variance report compares real purchases against invented stock.</span>
            </li>
            <li class="flex gap-2.5">
                <span class="text-slate-400 dark:text-slate-600">4.</span>
                <span><strong class="text-slate-900 dark:text-slate-100">The consumables list.</strong> The consumable item types are placeholders — the school's original sheet has none.</span>
            </li>
            <li class="flex gap-2.5">
                <span class="text-slate-400 dark:text-slate-600">5.</span>
                <span><strong class="text-slate-900 dark:text-slate-100">Change the MD password</strong> before anybody else touches the system.</span>
            </li>
        </ul>
    </x-card>
</div>
