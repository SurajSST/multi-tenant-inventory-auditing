@php $nothingPending = $myQueue->isEmpty() && $toOrder->isEmpty() && $toReceive->isEmpty(); @endphp

<div>
    <x-page-header title="Dashboard"
                   :subtitle="config('prativa.school_name') . ' — live from the register and the procurement trail.'">
        <x-slot:actions>
            @can('raise-demands')
                <x-button href="{{ route('demands.create') }}" wire:navigate>
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    New demand
                </x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Digest bar: system posture at a glance --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 px-5 py-3.5 shadow-sm dark:border-white/10 dark:from-slate-900 dark:to-slate-900/60">
        <div class="flex items-center gap-3">
            <div class="grid size-8 shrink-0 place-items-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-sky-500/10 dark:text-sky-400">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <p class="text-[13.5px] font-bold text-slate-900 dark:text-slate-100">Procurement &amp; Asset Health</p>
                <p class="text-xs text-slate-500 dark:text-slate-500">Fiscal year {{ \App\Support\FiscalYear::label() }} · separation-of-duties controls active in the database</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            @if ($myQueue->isNotEmpty())
                <x-badge :class="\App\Support\Tone::badge('amber')">{{ $myQueue->count() }} awaiting your decision</x-badge>
            @endif
            <x-badge :class="\App\Support\Tone::badge($stats['bill_mismatches'] ? 'rose' : 'emerald')">
                {{ $stats['bill_mismatches'] }} bill mismatch{{ $stats['bill_mismatches'] === 1 ? '' : 'es' }}
            </x-badge>
            @if ($stats['open_tokens'])
                <x-badge :class="\App\Support\Tone::badge('sky')">{{ $stats['open_tokens'] }} petty cash token{{ $stats['open_tokens'] === 1 ? '' : 's' }} open</x-badge>
            @endif
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @can('raise-demands')
            <a href="{{ route('demands.create') }}" wire:navigate
               class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md dark:border-white/10 dark:bg-slate-900 dark:hover:border-sky-500/40">
                <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-sky-500/10 dark:text-sky-400">
                    <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[13.5px] font-semibold text-slate-900 dark:text-slate-100">Raise demand</p>
                    <p class="truncate text-[11px] text-slate-500 dark:text-slate-500">Equipment requisition</p>
                </div>
            </a>
        @endcan

        @can('enter-counts')
            <a href="{{ route('inventory.count') }}" wire:navigate
               class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md dark:border-white/10 dark:bg-slate-900 dark:hover:border-emerald-500/40">
                <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[13.5px] font-semibold text-slate-900 dark:text-slate-100">Audit stock</p>
                    <p class="truncate text-[11px] text-slate-500 dark:text-slate-500">Physical block count</p>
                </div>
            </a>
        @endcan

        @can('handle-accounts')
            <a href="{{ route('petty-cash.issue') }}" wire:navigate
               class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md dark:border-white/10 dark:bg-slate-900 dark:hover:border-amber-500/40">
                <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                    <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2M21 12H9m12 0l-3-3m3 3l-3 3" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[13.5px] font-semibold text-slate-900 dark:text-slate-100">Petty cash</p>
                    <p class="truncate text-[11px] text-slate-500 dark:text-slate-500">Issue a token</p>
                </div>
            </a>
        @endcan

        <a href="{{ route('inventory.register') }}" wire:navigate
           class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:border-white/10 dark:bg-slate-900">
            <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400">
                <svg class="size-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-[13.5px] font-semibold text-slate-900 dark:text-slate-100">Stock register</p>
                <p class="truncate text-[11px] text-slate-500 dark:text-slate-500">Full asset catalogue</p>
            </div>
        </a>
    </div>

    {{-- KPI stats --}}
    <div class="mb-6 grid grid-cols-2 gap-3.5 lg:grid-cols-6">
        <x-stat label="Durable assets" :value="number_format($stats['durable_units'])" note="1 yr+ lifespan"
                :href="route('inventory.register', ['lifespan' => 'DURABLE'])" />
        <x-stat label="Consumables" :value="number_format($stats['consumable_units'])" note="under 1 yr"
                :href="route('inventory.register', ['lifespan' => 'CONSUMABLE'])" />
        <x-stat label="Awaiting approval" :value="$stats['pending_approvals']"
                :tone="$stats['pending_approvals'] ? 'amber' : 'slate'" note="in the approval chain" :href="route('demands.index')" />
        <x-stat label="Billed to date" :value="\App\Support\Money::format($stats['total_billed'])"
                :note="$stats['bills_entered'] . ' verified bills'" />
        <x-stat label="Bill mismatches" :value="$stats['bill_mismatches']"
                :tone="$stats['bill_mismatches'] ? 'rose' : 'emerald'" note="flagged for accounts"
                :href="auth()->user()->can('handle-accounts') ? route('bills.index') : null" />
        <x-stat label="Open tokens" :value="$stats['open_tokens']" :tone="$stats['open_tokens'] ? 'sky' : 'slate'"
                note="petty cash unpaid" :href="auth()->user()->can('handle-accounts') ? route('petty-cash.index') : null" />
    </div>

    <div class="mb-6 grid gap-5 lg:grid-cols-2">

        {{-- Action required --}}
        <x-card title="Action required" :flush="true">
            <div class="{{ $nothingPending ? '' : 'divide-y divide-slate-100 dark:divide-white/5' }}">
                @if ($myQueue->isNotEmpty())
                    <a href="{{ route('demands.queue') }}" wire:navigate
                       class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-amber-50/60 dark:hover:bg-amber-500/5">
                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1 text-[13.5px]">
                            <strong class="font-semibold text-slate-900 dark:text-slate-100">{{ $myQueue->count() }} demand form{{ $myQueue->count() === 1 ? '' : 's' }}</strong>
                            <span class="text-slate-500 dark:text-slate-400"> waiting on your approval</span>
                        </span>
                        <svg class="size-4 shrink-0 text-slate-400 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif

                @if ($toOrder->isNotEmpty())
                    <a href="{{ route('orders.index') }}" wire:navigate
                       class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-indigo-50/60 dark:hover:bg-sky-500/5">
                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-sky-500/10 dark:text-sky-400">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1 text-[13.5px]">
                            <strong class="font-semibold text-slate-900 dark:text-slate-100">{{ $toOrder->count() }} approved demand{{ $toOrder->count() === 1 ? '' : 's' }}</strong>
                            <span class="text-slate-500 dark:text-slate-400"> not yet ordered</span>
                        </span>
                        <svg class="size-4 shrink-0 text-slate-400 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif

                @if ($toReceive->isNotEmpty())
                    <a href="{{ route('orders.index') }}" wire:navigate
                       class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-indigo-50/60 dark:hover:bg-sky-500/5">
                        <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-sky-500/10 dark:text-sky-400">
                            <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1 text-[13.5px]">
                            <strong class="font-semibold text-slate-900 dark:text-slate-100">{{ $toReceive->count() }} order{{ $toReceive->count() === 1 ? '' : 's' }}</strong>
                            <span class="text-slate-500 dark:text-slate-400"> placed but not verified</span>
                        </span>
                        <svg class="size-4 shrink-0 text-slate-400 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif

                @if ($nothingPending)
                    <x-empty title="You are all caught up" note="No pending approvals or unverified orders need your attention right now." />
                @endif
            </div>
        </x-card>

        {{-- Spend chart with tabs for Procurement vs Petty Cash --}}
        <div x-data="{ chartTab: 'procurement' }">
            <x-card>
                <x-slot:header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100" x-text="chartTab === 'procurement' ? 'Monthly Procurement Spend' : 'Monthly Petty Cash Float'"></h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400" x-text="chartTab === 'procurement' ? '{{ $spendTrend['text'] ?? 'Committed procurement, by order date' }}' : 'Settled reimbursements and float payouts'"></p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="flex rounded-lg border border-slate-200 bg-slate-100 p-0.5 dark:border-white/10 dark:bg-white/5">
                                <button type="button" @click="chartTab = 'procurement'"
                                        :class="chartTab === 'procurement' ? 'bg-white text-indigo-600 shadow-sm dark:bg-white/10 dark:text-sky-300' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400'"
                                        class="rounded-md px-2.5 py-1 text-xs font-semibold transition-all">
                                    Orders
                                </button>
                                <button type="button" @click="chartTab = 'petty'"
                                        :class="chartTab === 'petty' ? 'bg-white text-amber-600 shadow-sm dark:bg-white/10 dark:text-amber-300' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400'"
                                        class="rounded-md px-2.5 py-1 text-xs font-semibold transition-all">
                                    Petty Cash
                                </button>
                            </div>
                            <x-badge>FY {{ \App\Support\FiscalYear::label() }}</x-badge>
                        </div>
                    </div>
                </x-slot:header>

                <div x-show="chartTab === 'procurement'">
                    <x-charts.spend-bar :data="$spend" />
                </div>
                <div x-show="chartTab === 'petty'" x-cloak>
                    <x-charts.petty-cash-bar :data="$pettySpend" />
                </div>
            </x-card>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        @if ($stats['by_category']->isNotEmpty())
            <x-card title="Stock distribution" subtitle="Units on the register, by category">
                <x-slot:actions>
                    <a href="{{ route('inventory.register') }}" wire:navigate class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-300 dark:hover:text-sky-400">
                        Full register →
                    </a>
                </x-slot:actions>
                <x-charts.category-bars :data="$stats['by_category']" />
            </x-card>
        @endif

        <x-card title="Recent activity" subtitle="Who did what, most recent first" :flush="true">
            @can('view-audit-trail')
                <x-slot:actions>
                    <a href="{{ route('audit.index') }}" wire:navigate class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-300 dark:hover:text-sky-400">
                        Audit trail →
                    </a>
                </x-slot:actions>
            @endcan

            <ul class="divide-y divide-slate-100 dark:divide-white/5">
                @forelse ($stats['recent_activity']->take(7) as $entry)
                    <li class="flex items-baseline gap-2 px-5 py-3 text-[13px]">
                        <span class="min-w-0 flex-1">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ $entry->actor?->full_name ?? 'System' }}</span>
                            <span class="text-slate-500 dark:text-slate-500"> {{ $entry->detail }}</span>
                        </span>
                        <time class="tnum shrink-0 text-[11px] text-slate-400 dark:text-slate-600">{{ $entry->at->diffForHumans(short: true) }}</time>
                    </li>
                @empty
                    <x-empty title="Nothing has happened yet" note="Actions appear here the moment somebody takes them." />
                @endforelse
            </ul>
        </x-card>
    </div>

    @if ($stats['low_stock']->isNotEmpty())
        <x-card class="mt-5" title="Below reorder level" :subtitle="$stats['low_stock']->count() . ' consumable item(s)'" :flush="true">
            <ul class="divide-y divide-slate-100 sm:columns-2 dark:divide-white/5">
                @foreach ($stats['low_stock'] as $item)
                    <li class="flex items-baseline justify-between gap-3 px-5 py-2.5">
                        <span class="min-w-0 truncate text-[13.5px] text-slate-700 dark:text-slate-300">{{ $item->name }}</span>
                        <span class="tnum shrink-0 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ $item->on_hand }} / {{ $item->reorder_level }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</div>
