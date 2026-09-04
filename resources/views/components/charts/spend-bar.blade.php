@props(['data'])

@php
    $max = max($data->max('amount') ?: 0, 1);
    $total = \App\Support\Money::sum($data->pluck('amount'));

    $width = 560;
    $height = 175;
    $padTop = 16;
    $padBottom = 28;
    $chartHeight = $height - $padTop - $padBottom;
    $barWidth = 44;
    $count = $data->count();
    $gap = $count > 1 ? ($width - 40 - $count * $barWidth) / ($count - 1) : 0;
    $latest = $count - 1;
@endphp

<div class="space-y-3">
    <div class="flex items-center justify-between">
        <div>
            <p class="font-mono text-[10.5px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                Procurement spend — last {{ $count }} months
            </p>
            <x-money :amount="$total" class="mt-0.5 block text-xl font-bold text-slate-900 dark:text-white" />
        </div>
        <span class="rounded-md bg-indigo-50 px-2 py-0.5 font-mono text-[11px] font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-sky-300">
            Committed POs
        </span>
    </div>

    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="block w-full overflow-visible select-none" style="max-height: 185px" role="img"
         aria-label="Monthly procurement spend bar chart">
        <defs>
            <linearGradient id="spendGradActive" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#38BDF8" />
                <stop offset="100%" stop-color="#0284C7" />
            </linearGradient>
            <linearGradient id="spendGradDefault" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#94A3B8" />
                <stop offset="100%" stop-color="#64748B" />
            </linearGradient>
        </defs>

        {{-- Subtle Gridlines --}}
        <line x1="20" y1="{{ $padTop }}" x2="{{ $width - 20 }}" y2="{{ $padTop }}"
              class="stroke-slate-200 dark:stroke-white/[0.08]" stroke-dasharray="3 3" />
        <line x1="20" y1="{{ $padTop + $chartHeight / 2 }}" x2="{{ $width - 20 }}" y2="{{ $padTop + $chartHeight / 2 }}"
              class="stroke-slate-200 dark:stroke-white/[0.08]" stroke-dasharray="3 3" />
        <line x1="20" y1="{{ $height - $padBottom }}" x2="{{ $width - 20 }}" y2="{{ $height - $padBottom }}"
              class="stroke-slate-300 dark:stroke-white/15" stroke-width="1.5" />

        @foreach ($data as $i => $point)
            @php
                $h = max(6, ((float) $point['amount'] / $max) * $chartHeight);
                $x = 20 + $i * ($barWidth + $gap);
                $y = $height - $padBottom - $h;
                $isLatest = $i === $latest;
            @endphp
            <g class="group cursor-pointer">
                <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barWidth }}" height="{{ $h }}" rx="6" ry="6"
                      class="transition-all duration-200 group-hover:opacity-90 group-hover:brightness-110"
                      fill="{{ $isLatest ? 'url(#spendGradActive)' : 'url(#spendGradDefault)' }}">
                    <title>{{ $point['label'] }}: {{ \App\Support\Money::npr($point['amount']) }} ({{ $point['orders'] }} orders)</title>
                </rect>

                @if ($isLatest)
                    <text x="{{ $x + $barWidth / 2 }}" y="{{ $y - 6 }}" text-anchor="middle"
                          class="fill-sky-600 font-mono text-[11px] font-bold dark:fill-sky-400 drop-shadow-sm">
                        {{ \App\Support\Money::format($point['amount']) }}
                    </text>
                @endif

                <text x="{{ $x + $barWidth / 2 }}" y="{{ $height - 8 }}" text-anchor="middle"
                      class="{{ $isLatest ? 'fill-sky-600 font-bold dark:fill-sky-400' : 'fill-slate-500 dark:fill-slate-400' }} font-mono text-[12px] group-hover:font-semibold">
                    {{ $point['label'] }}
                </text>
            </g>
        @endforeach
    </svg>
</div>
