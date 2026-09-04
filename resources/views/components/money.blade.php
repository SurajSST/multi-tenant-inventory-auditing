@props(['amount', 'bare' => false])

<span {{ $attributes->merge(['class' => 'tnum whitespace-nowrap']) }}>{{ $bare
    ? \App\Support\Money::format($amount)
    : \App\Support\Money::npr($amount) }}</span>
