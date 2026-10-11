@extends('errors.layout')

@section('title', '403 · Access Denied')
@section('code', '403')
@section('badge-class', 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400')
@section('dot-class', 'bg-rose-500')
@section('icon-bg', 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400')

@section('icon')
    <svg class="size-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
    </svg>
@endsection

@section('heading', 'Access Restricted')

@section('message')
    {{ $exception?->getMessage() ?: "You do not have permission to view this page or perform this action with your current role." }}
@endsection

@section('detail')
    <div class="space-y-1">
        <p class="font-medium text-slate-800 dark:text-slate-200">Why was this blocked?</p>
        <p>In this system, <strong>separation of duties</strong> is enforced. No single person can request, approve, order, verify delivery, and disburse money alone. If your posting or role requires access to this function, please request a role adjustment from the Super Admin.</p>
    </div>
@endsection
