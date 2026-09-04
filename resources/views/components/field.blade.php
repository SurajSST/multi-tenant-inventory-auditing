@props(['label', 'for' => null, 'hint' => null, 'error' => null, 'required' => false])

<div {{ $attributes->only('class') }}>
    <label @if ($for) for="{{ $for }}" @endif class="block text-sm font-medium text-slate-700 dark:text-slate-300">
        {{ $label }}
        @if ($required) <span class="text-rose-500 dark:text-rose-400">*</span> @endif
    </label>

    <div class="mt-1.5">{{ $slot }}</div>

    @if ($hint && ! $error)
        <p class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-500">{{ $hint }}</p>
    @endif

    @if ($error)
        <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $error }}</p>
    @endif
</div>
