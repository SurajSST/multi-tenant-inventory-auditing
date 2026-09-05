@props([
    'signatures' => [],
])

@php
    $count = count($signatures);
    $colsClass = match($count) {
        1 => 'grid-cols-1',
        2 => 'grid-cols-2',
        3 => 'grid-cols-3',
        4 => 'grid-cols-4',
        default => 'grid-cols-3',
    };
@endphp

<div class="print-only hidden print:block mt-10 pt-4 break-inside-avoid page-break-inside-avoid">
    <div class="grid {{ $colsClass }} gap-6 text-center">
        @foreach ($signatures as $sig)
            <div class="flex flex-col items-center justify-end">
                <div class="w-full border-t-2 border-slate-900 pt-2">
                    <p class="text-[11.5px] font-bold uppercase tracking-wider text-slate-950">{{ $sig['role'] ?? 'Signatory' }}</p>
                    <p class="text-[11px] font-semibold text-slate-900 mt-0.5">{{ $sig['name'] ?? '____________________' }}</p>
                    @if (!empty($sig['designation']))
                        <p class="text-[9.5px] text-slate-700">{{ $sig['designation'] }}</p>
                    @endif
                    <p class="text-[9.5px] text-slate-500 mt-1 font-mono">Date: {{ $sig['date'] ?? '_______________' }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8 border-t border-dashed border-slate-300 pt-2 flex items-center justify-between text-[9px] text-slate-500">
        <span>Prativa Secondary School · Official Internal Inventory System</span>
        <span>Generated: {{ now()->format('d M Y, H:i') }}</span>
        <span>Office Stamp / छाप</span>
    </div>
</div>
