@props(['title' => 'Nothing here', 'note' => null])

<div class="px-5 py-14 text-center">
    <p class="font-mono text-sm font-semibold text-slate-700 dark:text-slate-300">{{ $title }}</p>
    @if ($note)
        <p class="mx-auto mt-1.5 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-500">{{ $note }}</p>
    @endif
    @isset($slot)
        <div class="mt-4">{{ $slot }}</div>
    @endisset
</div>
