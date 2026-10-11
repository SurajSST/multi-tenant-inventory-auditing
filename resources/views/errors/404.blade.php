@extends('errors.layout')

@section('title', '404 · Page Not Found')
@section('code', '404')
@section('badge-class', 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300')
@section('dot-class', 'bg-sky-500')
@section('icon-bg', 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400')

@section('icon')
    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
    </svg>
@endsection

@section('heading', 'Page Not Found')

@section('message')
    {{ $exception?->getMessage() ?: "The page, document, or record you requested could not be found." }}
@endsection

@section('detail')
    <p>The link might be mistyped, the document ID might have been withdrawn, or the record belongs to another school/tenant. Verify the reference number and try searching from the dashboard.</p>
@endsection
