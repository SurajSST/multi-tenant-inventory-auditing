@extends('errors.layout')

@section('title', '419 · Page Expired')
@section('code', '419')
@section('badge-class', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300')
@section('dot-class', 'bg-amber-500')
@section('icon-bg', 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400')

@section('icon')
    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>
@endsection

@section('heading', 'Session Expired')

@section('message', 'Your security session has expired due to inactivity. Please refresh the page or sign in again to continue.')

@section('detail')
    <p>To prevent unauthorized access or replay of financial transactions, authentication tokens expire automatically when a window is left open unattended.</p>
@endsection

@section('extra-actions')
    <button type="button" onclick="window.location.reload()"
            class="inline-flex min-h-[40px] items-center justify-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 shadow-sm transition hover:bg-amber-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20">
        <svg class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
        Refresh page
    </button>
@endsection
