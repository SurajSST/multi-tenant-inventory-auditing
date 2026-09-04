<select {{ $attributes->merge([
    'class' => 'block w-full min-h-[38px] rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm '.
        'focus:border-indigo-500 focus:ring-indigo-500 '.
        'dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500 '.
        '[&>option]:bg-white dark:[&>option]:bg-slate-900',
]) }}>{{ $slot }}</select>
