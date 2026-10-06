@props([
    'action',
    'params' => [],
    'title' => 'Are you sure?',
    'message',
    'confirmLabel' => 'Confirm',
    'tone' => 'danger',
    'triggerClass' => 'inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:text-slate-200 dark:hover:bg-white/10',
])

@php($titleId = 'confirm-dialog-title-'.md5($action.json_encode($params)))

<span x-data="{ open: false, action: @js($action), params: @js($params) }"
      x-on:keydown.escape.window="open = false">
    <button type="button" x-on:click="open = true" x-bind:aria-expanded="open" {{ $attributes->merge(['class' => $triggerClass]) }}>
        {{ $slot }}
    </button>

    <div x-cloak x-show="open" x-transition.opacity
         class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm"
         x-on:click.self="open = false"
         role="presentation">
            <section x-show="open" x-transition
                     x-on:click.stop
                     role="alertdialog" aria-modal="true" aria-labelledby="{{ $titleId }}"
                     class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-white/10">
                <div class="flex gap-4 p-5 sm:p-6">
                    <div @class([
                        'flex size-10 shrink-0 items-center justify-center rounded-full',
                        'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300' => $tone === 'danger',
                        'bg-indigo-50 text-indigo-600 dark:bg-sky-500/10 dark:text-sky-300' => $tone !== 'danger',
                    ])>
                        @if ($tone === 'danger')
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                        @else
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h2 id="{{ $titleId }}" class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $title }}</h2>
                        <p class="mt-1.5 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $message }}</p>
                    </div>
                </div>
                <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50/80 px-5 py-4 sm:flex-row sm:justify-end dark:border-white/10 dark:bg-white/[.03]">
                    <button type="button" x-on:click="open = false"
                            class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        Cancel
                    </button>
                    <button type="button" x-on:click="open = false; $wire.$call(action, ...params)"
                            @class([
                                'inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-semibold text-white shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
                                'bg-rose-600 hover:bg-rose-500 focus-visible:outline-rose-600 dark:bg-rose-500 dark:hover:bg-rose-400' => $tone === 'danger',
                                'bg-indigo-600 hover:bg-indigo-500 focus-visible:outline-indigo-600 dark:bg-sky-500 dark:hover:bg-sky-400 dark:focus-visible:outline-sky-400' => $tone !== 'danger',
                            ])>
                        {{ $confirmLabel }}
                    </button>
                </footer>
            </section>
    </div>
</span>
