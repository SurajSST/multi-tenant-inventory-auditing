@extends('errors.layout')

@section('title', '503 · Service Unavailable')
@section('code', '503')
@section('badge-class', 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300')
@section('dot-class', 'bg-indigo-500')
@section('icon-bg', 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400')

@section('icon')
    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.07a4.5 4.5 0 0 0 4.486-6.32l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.32 4.486c.098.475.103.97-.024 1.44" />
    </svg>
@endsection

@section('heading', 'Maintenance in Progress')

@section('message')
    {{ $exception?->getMessage() ?: "The inventory system is temporarily undergoing scheduled maintenance or database optimization. Please check back shortly." }}
@endsection

@section('detail')
    <p>Routine maintenance ensures inventory calculations, fiscal year Bikram Sambat rolls, and database integrity rules remain consistent across all school tenants.</p>
@endsection

@section('extra-actions')
    <button type="button" onclick="window.location.reload()"
            class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-800 shadow-sm transition hover:bg-indigo-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300 dark:hover:bg-indigo-500/20">
        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
        Check again
    </button>
@endsection
