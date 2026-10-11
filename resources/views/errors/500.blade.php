@extends('errors.layout')

@section('title', '500 · Server Error')
@section('code', '500')
@section('badge-class', 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400')
@section('dot-class', 'bg-rose-500')
@section('icon-bg', 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400')

@section('icon')
    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
    </svg>
@endsection

@section('heading', 'Internal Server Error')

@section('message')
    An unexpected error occurred while processing your request. The system administrator has been alerted and this incident has been logged.
@endsection

@section('detail')
    <p>Database constraints, triggers, and state rules protect the stock register from inconsistent updates. If you were submitting a financial voucher or count sheet, check the audit trail or history before re-entering.</p>
@endsection
