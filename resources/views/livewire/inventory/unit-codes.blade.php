<div>
    <x-page-header :title="$itemType->name"
                   subtitle="Every physical unit expanded to its own code. Numbering runs across the blocks in block order, matching the school's original sheet.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('inventory.history', $itemType) }}" wire:navigate>Count history</x-button>
            <x-button variant="secondary" href="{{ route('export.unit-list') }}">Export all units</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-stat label="Code prefix" :value="$itemType->code_prefix" />
        <x-stat label="Units on the register" :value="number_format($total)" />
        <x-stat label="Slot" :value="$itemType->lifespan->label()" />
        <x-stat label="Category" :value="$itemType->category->name" />
    </div>

    <x-card :flush="true" title="Unit codes"
            subtitle="{{ $total ? $itemType->code_prefix . '.1 through ' . $itemType->code_prefix . '.' . $total : 'No units on the register yet' }}">
        @if ($units->isEmpty())
            <x-empty title="No units on the register"
                     note="Once an auditor counts this item, or an order for it is received, the units appear here with their codes." />
        @else
            <div class="p-5">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    @foreach ($units as $unit)
                        <div class="rounded-lg border border-slate-200 px-3 py-2 dark:border-white/10">
                            <code class="tnum block text-xs font-medium text-slate-900 dark:text-slate-100">{{ $unit['unit_code'] }}</code>
                            <span class="text-[11px] text-slate-500 dark:text-slate-500">{{ $unit['block'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-card>
</div>
