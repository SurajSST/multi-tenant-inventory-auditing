@props([
    'title',
    'nepaliTitle' => null,
    'ref' => null,
    'date' => null,
    'fiscalYear' => null,
    'department' => null,
    'status' => null,
    'vendor' => null,
])

@php
    $currentSchool = app(\App\Tenancy\TenantContext::class)->current();
    $schoolName = $currentSchool?->name ?? config('prativa.school_name', 'Prativa Secondary School');
@endphp

<div class="print-only hidden print:block mb-6 border-b-2 border-slate-900 pb-4">
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3.5">
            @if ($currentSchool?->hasCustomLogo())
                <img src="{{ $currentSchool->logo_url }}" alt="{{ $schoolName }}" class="h-14 w-auto max-w-[90px] object-contain" />
            @else
                <img src="/img/logo/prativalogo.png" alt="{{ $schoolName }}" class="h-14 w-auto max-w-[90px] object-contain" />
            @endif
            <div>
                <h1 class="font-heading text-lg font-bold uppercase tracking-tight text-slate-950">{{ $schoolName }}</h1>
                <p class="text-xs text-slate-700 font-medium">Pokhara, Nepal · Stock Auditing & Procurement Department</p>
                <p class="text-[11px] text-slate-500">Official Institutional Paperwork</p>
            </div>
        </div>
        <div class="text-right text-xs">
            @if ($fiscalYear)
                <p class="font-mono text-slate-600">Fiscal Year: <strong class="text-slate-950">{{ $fiscalYear }}</strong></p>
            @endif
            <p class="text-slate-600">Date: <strong class="text-slate-950">{{ $date ?? now()->format('d M Y') }}</strong></p>
            @if ($ref)
                <p class="font-mono text-[13px] font-bold text-slate-950 mt-1">Ref: {{ $ref }}</p>
            @endif
        </div>
    </div>

    <div class="mt-3.5 border-t border-slate-300 pt-2 text-center">
        <h2 class="font-heading text-base font-bold uppercase tracking-wider text-slate-950">
            {{ $title }}
            @if ($nepaliTitle)
                <span class="text-sm font-normal text-slate-700 ml-1">({{ $nepaliTitle }})</span>
            @endif
        </h2>
    </div>

    @if ($department || $status || $vendor)
        <div class="mt-2.5 flex flex-wrap items-center justify-between gap-3 rounded border border-slate-300 bg-slate-50 px-3.5 py-1.5 text-xs text-slate-800">
            @if ($department)
                <div><span class="text-slate-500">Department:</span> <strong class="text-slate-900">{{ $department }}</strong></div>
            @endif
            @if ($vendor)
                <div><span class="text-slate-500">Vendor:</span> <strong class="text-slate-900">{{ $vendor }}</strong></div>
            @endif
            @if ($status)
                <div><span class="text-slate-500">Status:</span> <strong class="text-slate-900 uppercase font-mono">{{ $status }}</strong></div>
            @endif
        </div>
    @endif
</div>
