@extends('errors.layout')

@section('title', ($exception?->getStatusCode() ?? 'Error') . ' · ' . config('prativa.school_name', 'Prativa Secondary School'))
@section('code', (string) ($exception?->getStatusCode() ?? 'Error'))
@section('badge-class', 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-300')
@section('dot-class', 'bg-slate-400')
@section('icon-bg', 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-300')

@section('icon')
    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
    </svg>
@endsection

@section('heading', 'HTTP ' . ($exception?->getStatusCode() ?? 'Error'))

@section('message')
    {{ $exception?->getMessage() ?: "An error occurred while processing your request." }}
@endsection
