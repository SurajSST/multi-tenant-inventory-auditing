@extends('errors.layout')

@section('title', '429 · Too Many Requests')
@section('code', '429')
@section('badge-class', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300')
@section('dot-class', 'bg-amber-500')
@section('icon-bg', 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400')

@section('icon')
    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.5a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm0 13.5a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />
    </svg>
@endsection

@section('heading', 'Too Many Requests')

@section('message')
    {{ $exception?->getMessage() ?: "You have sent too many requests in a short period. Please slow down and wait a few moments before trying again." }}
@endsection

@section('detail')
    <p>Rate limiting protects system availability and prevents automated script floods on sensitive endpoints like authentication, stock counting, and exports.</p>
@endsection
