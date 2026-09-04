@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'size' => 'md',
    // Name the Livewire action this button triggers, and it will disable itself
    // and show a spinner while that action is in flight. On a school's network
    // a save can take a moment, and a button that looks untouched invites a
    // second click — which, for issuing a token or placing an order, is exactly
    // the thing not to invite.
    'busy' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 rounded-lg font-semibold transition-all active:scale-[.985] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-45 disabled:pointer-events-none';

    $sizes = [
        'xs' => 'min-h-[26px] px-2.5 py-1 text-xs',
        'sm' => 'min-h-[32px] px-3 text-xs',
        'md' => 'min-h-[38px] px-3.5 text-sm',
        'lg' => 'min-h-[44px] px-4 text-base',
    ];

    $sizeClass = $sizes[$size] ?? $sizes['md'];

    $variants = [
        'primary' => 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500 hover:shadow-md focus-visible:outline-indigo-600 dark:bg-sky-500 dark:hover:bg-sky-400 dark:focus-visible:outline-sky-400',
        'secondary' => 'bg-white text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-700 dark:hover:bg-slate-800',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-500 focus-visible:outline-rose-600 dark:bg-rose-500 dark:hover:bg-rose-400',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white',
    ];

    $classes = $base.' '.$sizeClass.' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}"
            @if ($busy)
                wire:loading.attr="disabled"
                wire:target="{{ $busy }}"
            @endif
            {{ $attributes->merge(['class' => $classes]) }}>
        @if ($busy)
            <svg wire:loading wire:target="{{ $busy }}" class="size-3.5 shrink-0 animate-spin"
                 viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-90" fill="currentColor"
                      d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
