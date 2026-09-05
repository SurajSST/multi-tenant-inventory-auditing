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
</div>
