<input {{ $attributes->merge([
    'class' => 'block w-full min-h-[38px] rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm '.
        'placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-500 '.
        'dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-600 '.
        'dark:focus:border-sky-500 dark:focus:ring-sky-500 dark:disabled:bg-slate-800/50',
]) }} />
