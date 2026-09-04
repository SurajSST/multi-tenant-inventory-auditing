<div class="space-y-6">
    <x-page-header title="Platform Console"
                   subtitle="Global overview across all schools. Manage multi-tenant instances, provision schools, configure logos, and monitor system-wide procurement health.">
        <x-slot:actions>
            <x-button wire:click="newSchool">
                <svg class="-ml-1 mr-1.5 size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Set up a school
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-errors />

    {{-- Global KPI Metric Cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Total Schools --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Schools</span>
                <span class="grid size-8 place-items-center rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400">
                    <svg class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $stats['total_schools'] }}</span>
                <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ $stats['active_schools'] }} active</span>
                @if ($stats['suspended_schools'] > 0)
                    <span class="text-xs font-medium text-rose-500 dark:text-rose-400">({{ $stats['suspended_schools'] }} suspended)</span>
                @endif
            </div>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Multi-tenant school instances</p>
        </div>

        {{-- Enrolled Staff --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Enrolled Staff</span>
                <span class="grid size-8 place-items-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                    <svg class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ number_format($stats['total_staff']) }}</span>
            </div>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Active postings across all schools</p>
        </div>

        {{-- Total Committed Spend --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Committed Spend</span>
                <span class="grid size-8 place-items-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <svg class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-3">
                <x-money :amount="$stats['total_committed']" class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100" />
            </div>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ $stats['total_orders'] }} purchase orders placed</p>
        </div>

        {{-- Flagged Mismatches --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Flagged Bills</span>
                <span class="grid size-8 place-items-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                    <svg class="size-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </span>
            </div>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-2xl font-bold tracking-tight {{ $stats['total_flagged'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100' }}">
                    {{ $stats['total_flagged'] }}
                </span>
                @if ($stats['total_flagged'] > 0)
                    <span class="text-xs font-medium text-rose-600 dark:text-rose-400">Requires attention</span>
                @else
                    <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">All clean</span>
                @endif
            </div>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">3-way mismatch variances</p>
        </div>
    </div>

    {{-- School Form Modal / Card (Create or Edit) --}}
    @if ($showForm)
        <x-card :title="$editingTenantId ? 'Edit School Details' : 'Set Up a New School'">
            <form wire:submit="{{ $editingTenantId ? 'update' : 'create' }}" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="School Name" required :error="$errors->first('name')">
                        <x-input wire:model.live.debounce.400ms="name" placeholder="Everest English Academy" />
                    </x-field>

                    <x-field label="Short Code (Slug)" required hint="Used in URLs and references (lowercase, hyphens)." :error="$errors->first('slug')">
                        <x-input wire:model="slug" placeholder="everest" />
                    </x-field>

                    <x-field label="Short Name" hint="Optional. Displayed in compact badges & mobile headers." :error="$errors->first('shortName')">
                        <x-input wire:model="shortName" placeholder="Everest" />
                    </x-field>

                    <x-field label="Campus Address" hint="Optional physical location." :error="$errors->first('address')">
                        <x-input wire:model="address" placeholder="e.g. Kathmandu, Nepal" />
                    </x-field>
                </div>

                {{-- School Logo Section --}}
                <div class="border-t border-slate-200 pt-5 dark:border-white/10">
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">School Logo &amp; Branding</p>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        Upload a PNG/JPEG logo or provide an image URL. This appears across the sidebar, reports, and logins for this school.
                    </p>

                    <div class="mt-4 grid gap-5 sm:grid-cols-2 items-start">
                        <x-field label="Upload Logo File" hint="Max 2MB. Transparent PNG recommended." :error="$errors->first('logoFile')">
                            <input type="file" wire:model="logoFile" accept="image/*"
                                   class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-white/10 dark:file:text-sky-300" />
                        </x-field>

                        <x-field label="Or Logo URL" hint="Direct web URL to image file." :error="$errors->first('logoUrl')">
                            <x-input wire:model="logoUrl" placeholder="https://example.com/logo.png" />
                        </x-field>
                    </div>

                    {{-- Live Preview --}}
                    @if ($logoFile || $logoUrl)
                        <div class="mt-4 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-white/10 dark:bg-white/5">
                            <div class="grid size-12 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white p-1 dark:border-white/10 dark:bg-slate-900">
                                @if ($logoFile)
                                    <img src="{{ $logoFile->temporaryUrl() }}" alt="Logo preview" class="size-full object-contain" />
                                @elseif ($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="Logo preview" class="size-full object-contain" />
                                @endif
                            </div>
                            <div class="text-xs">
                                <span class="font-medium text-slate-900 dark:text-slate-100">Logo Preview</span>
                                <span class="block text-slate-500 dark:text-slate-400">This logo will be displayed on this school's interface.</span>
                            </div>
                        </div>
                    @endif
                </div>

                @unless ($editingTenantId)
                    <div class="border-t border-slate-200 pt-5 dark:border-white/10">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Initial Administrator</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            They will receive the Super Admin role at this school to set up staff, categories, and inventory.
                        </p>

                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <x-field label="Admin Full Name" required :error="$errors->first('adminName')">
                                <x-input wire:model="adminName" placeholder="e.g. Ram Bahadur Thapa" />
                            </x-field>

                            <x-field label="Admin Email Address" required :error="$errors->first('adminEmail')">
                                <x-input wire:model="adminEmail" type="email" placeholder="admin@everest.edu.np" />
                            </x-field>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-5 dark:border-white/10">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">What it starts with</p>

                        <label class="mt-3 flex cursor-pointer items-start gap-3 rounded-lg border border-slate-200 px-3.5 py-3 transition hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5">
                            <input type="checkbox" wire:model="withCatalogue"
                                   class="mt-0.5 size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-white/20 dark:bg-slate-900" />
                            <span class="min-w-0">
                                <span class="block text-[13px] font-medium text-slate-900 dark:text-slate-100">
                                    Copy the standard catalogue
                                </span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                    Six blocks, the category tree and 54 item codes, ready to edit. Right for a school
                                    with much the same furniture. Leave it unticked if this school has its own buildings
                                    and its own inventory — it then starts with an empty register and builds its own
                                    in Setup.
                                </span>
                            </span>
                        </label>

                        <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            The approval ladder and the petty cash ceiling are set up either way — a school cannot
                            operate without them — and both are editable.
                            <strong class="font-semibold text-slate-700 dark:text-slate-300">Opening stock is always
                            left empty</strong>, because those figures are whatever this school's auditor counts.
                        </p>
                    </div>
                @endunless

                <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-white/10">
                    <x-button variant="secondary" wire:click="cancel">Cancel</x-button>
                    <x-button type="submit" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="{{ $editingTenantId ? 'update' : 'create' }}">
                            {{ $editingTenantId ? 'Save Changes' : 'Set the school up' }}
                        </span>
                        <span wire:loading wire:target="{{ $editingTenantId ? 'update' : 'create' }}">Saving…</span>
                    </x-button>
                </div>
            </form>
        </x-card>
    @endif

    {{-- Schools Table --}}
    <x-card :flush="true" title="Managed Schools ({{ $schools->count() }})">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-400">
                    <tr>
                        <th scope="col" class="px-5 py-3 font-medium">School / Brand</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">Staff</th>
                        {{-- The middle figures are a summary; the totals above already
                             carry them. They give way on narrow screens so that the
                             school and its actions always fit. --}}
                        <th scope="col" class="hidden px-4 py-3 text-right font-medium lg:table-cell">Items</th>
                        <th scope="col" class="hidden px-4 py-3 text-right font-medium xl:table-cell">Demands</th>
                        <th scope="col" class="hidden px-4 py-3 text-right font-medium xl:table-cell">Orders</th>
                        <th scope="col" class="hidden px-4 py-3 text-right font-medium lg:table-cell">Committed Spend</th>
                        <th scope="col" class="hidden px-4 py-3 text-right font-medium sm:table-cell">Flagged</th>
                        {{-- Pinned. With eight columns this sat off the right edge of
                             the scroll container, so the buttons existed but could not
                             be reached without knowing to scroll sideways. --}}
                        <th scope="col" class="sticky right-0 z-20 border-l border-slate-200 bg-slate-50 px-5 py-3 text-right font-medium dark:border-white/10 dark:bg-[#111C2E]">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @foreach ($schools as $school)
                        <tr wire:key="school-{{ $school->id }}"
                            class="group {{ $school->is_active ? '' : 'opacity-60' }} hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    {{-- School Logo Thumbnail --}}
                                    <div class="grid size-9 shrink-0 place-items-center rounded-lg border border-slate-200 bg-white p-1 shadow-sm overflow-hidden dark:border-white/10 dark:bg-slate-900">
                                        @if ($school->logo_url)
                                            <img src="{{ $school->logo_url }}" alt="{{ $school->name }}" class="size-full object-contain" />
                                        @else
                                            <span class="font-heading font-bold text-xs text-slate-500 dark:text-slate-400">
                                                {{ mb_substr($school->name, 0, 2) }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-slate-900 dark:text-slate-100 truncate">{{ $school->name }}</span>
                                            @unless ($school->is_active)
                                                <span class="inline-flex items-center rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/10 dark:bg-rose-500/10 dark:text-rose-400">
                                                    suspended
                                                </span>
                                            @endunless
                                        </div>
                                        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                            <span class="font-mono text-[11px] text-sky-600 dark:text-sky-400 font-medium">/{{ $school->slug }}</span>
                                            @if ($school->address)
                                                <span>·</span>
                                                <span class="truncate">{{ $school->address }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="tnum px-4 py-3.5 text-right font-medium text-slate-700 dark:text-slate-300">{{ $school->staff }}</td>
                            <td class="tnum hidden px-4 py-3.5 text-right font-medium text-slate-700 lg:table-cell dark:text-slate-300">{{ $school->items }}</td>
                            <td class="tnum hidden px-4 py-3.5 text-right font-medium text-slate-700 xl:table-cell dark:text-slate-300">{{ $school->demands }}</td>
                            <td class="tnum hidden px-4 py-3.5 text-right font-medium text-slate-700 xl:table-cell dark:text-slate-300">{{ $school->orders }}</td>
                            <td class="tnum hidden px-4 py-3.5 text-right font-semibold text-slate-900 lg:table-cell dark:text-slate-100">
                                <x-money :amount="$school->committed" />
                            </td>
                            <td class="tnum hidden px-4 py-3.5 text-right sm:table-cell">
                                @if ($school->flagged > 0)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                                        <span class="size-1.5 rounded-full bg-rose-500"></span>
                                        {{ $school->flagged }}
                                    </span>
                                @else
                                    <span class="text-slate-400 dark:text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="sticky right-0 z-10 border-l border-slate-200 bg-white px-5 py-3.5 text-right group-hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:group-hover:bg-[#111C2E]">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($school->is_active)
                                        <x-button size="sm" wire:click="enter('{{ $school->id }}')">
                                            Open
                                        </x-button>
                                    @endif

                                    <x-button variant="secondary" size="sm" wire:click="editSchool('{{ $school->id }}')">
                                        Edit
                                    </x-button>

                                    <button type="button" wire:click="toggleActive('{{ $school->id }}')"
                                            wire:confirm="{{ $school->is_active ? 'Suspend '.$school->name.'? Nobody there will be able to sign in.' : 'Resume '.$school->name.'?' }}"
                                            class="rounded-lg px-2.5 py-1.5 text-xs font-medium transition-colors {{ $school->is_active ? 'text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10' : 'text-emerald-600 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10' }}">
                                        {{ $school->is_active ? 'Suspend' : 'Resume' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- Platform Activity Trail --}}
    @if ($recentActivity->isNotEmpty())
        <x-card title="Platform Activity Trail" subtitle="Recent administrative and governance events across all schools.">
            <ul class="divide-y divide-slate-100 dark:divide-white/5">
                @foreach ($recentActivity as $event)
                    <li class="py-3 flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300">
                                <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ $event->detail }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    By <span class="font-medium text-slate-700 dark:text-slate-300">{{ $event->actor?->full_name ?? 'System' }}</span>
                                    · <span class="font-mono text-[11px]">{{ $event->action }}</span>
                                </p>
                            </div>
                        </div>
                        <span class="shrink-0 text-xs text-slate-400 dark:text-slate-500 font-mono">
                            {{ $event->at?->diffForHumans() ?? 'Just now' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</div>
