<div>
    <x-print-header
        title="Petty Cash Payment Voucher"
        nepaliTitle="खुद्रा खर्च भुक्तानी भौचर"
        :ref="$token->serial"
        :date="$token->issued_at->format('d M Y')"
        :fiscalYear="$token->fiscal_year"
        :status="$token->status->label()"
    />

    <x-page-header :title="$token->serial" subtitle="Petty cash token">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('petty-cash.print', ['token' => $token, 'autoprint' => 1]) }}" target="_blank">
                <svg class="size-4 shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.656h10.5Z" />
                </svg>
                Print Voucher
            </x-button>
            <x-button variant="secondary" href="{{ route('petty-cash.pdf', $token) }}">
                <svg class="size-4 shrink-0 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Download PDF
            </x-button>
            @if ($token->isOpen() && $token->issued_by_id !== auth()->id())
                <x-button wire:click="pay"
                          wire:confirm="Release {{ \App\Support\Money::npr($token->amount) }} to {{ $token->claimant_name }}?">
                    Mark paid
                </x-button>
            @endif
            <x-button variant="secondary" href="{{ route('petty-cash.index') }}" wire:navigate>All tokens</x-button>
        </x-slot:actions>
    </x-page-header>

    @error('token')
        <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-300">{{ $message }}</div>
    @enderror

    <div class="mx-auto max-w-2xl">
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-900/5 dark:bg-slate-900 dark:ring-white/10">

            <div class="border-b border-slate-200 px-6 py-5 text-center dark:border-white/10">
                <p class="text-sm font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ config('prativa.school_name') }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-500">Petty Cash Token</p>
                <p class="tnum mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ $token->serial }}</p>
                <div class="mt-2">
                    <x-badge :class="$token->status->badge()">{{ $token->status->label() }}</x-badge>
                </div>
            </div>

            <dl class="divide-y divide-slate-100 text-sm dark:divide-white/5">
                <div class="flex justify-between gap-4 px-6 py-3">
                    <dt class="text-slate-500 dark:text-slate-500">Amount</dt>
                    <dd><x-money :amount="$token->amount" class="text-lg font-semibold text-slate-900 dark:text-slate-100" /></dd>
                </div>
                <div class="flex justify-between gap-4 px-6 py-3">
                    <dt class="text-slate-500 dark:text-slate-500">Claimant</dt>
                    <dd class="font-medium text-slate-900 dark:text-slate-100">{{ $token->claimant_name }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-6 py-3">
                    <dt class="text-slate-500 dark:text-slate-500">Purpose</dt>
                    <dd class="text-right text-slate-900 dark:text-slate-100">{{ $token->purpose }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-6 py-3">
                    <dt class="text-slate-500 dark:text-slate-500">Bill number</dt>
                    <dd class="text-slate-900 dark:text-slate-100">{{ $token->bill_no }}</dd>
                </div>
                <div class="flex justify-between gap-4 px-6 py-3">
                    <dt class="text-slate-500 dark:text-slate-500">Vendor</dt>
                    <dd class="text-slate-900 dark:text-slate-100">{{ $token->vendor_name }}</dd>
                </div>
                @if ($token->bill_date)
                    <div class="flex justify-between gap-4 px-6 py-3">
                        <dt class="text-slate-500 dark:text-slate-500">Bill date</dt>
                        <dd class="text-slate-900 dark:text-slate-100">{{ $token->bill_date->format('d M Y') }}</dd>
                    </div>
                @endif
                <div class="flex justify-between gap-4 px-6 py-3">
                    <dt class="text-slate-500 dark:text-slate-500">Original bill sighted</dt>
                    <dd class="font-medium text-emerald-700 dark:text-emerald-400">Yes, by the issuer</dd>
                </div>
                <div class="flex justify-between gap-4 px-6 py-3">
                    <dt class="text-slate-500 dark:text-slate-500">Ceiling in force at issue</dt>
                    <dd><x-money :amount="$token->ceiling_at_issue" class="text-slate-900 dark:text-slate-100" /></dd>
                </div>
                <div class="flex justify-between gap-4 px-6 py-3">
                    <dt class="text-slate-500 dark:text-slate-500">Fiscal year</dt>
                    <dd class="text-slate-900 dark:text-slate-100">{{ $token->fiscal_year }}</dd>
                </div>
            </dl>

            <div class="grid gap-4 border-t border-slate-200 bg-slate-50 px-6 py-5 sm:grid-cols-2 dark:border-white/10 dark:bg-white/5">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-500">Issued by</p>
                    <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $token->issuedBy->full_name }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-500">{{ $token->issuedBy->designation }}</p>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $token->issued_at->format('d M Y, H:i') }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-500">Payment released by</p>
                    @if ($token->paidBy)
                        <p class="mt-1 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $token->paidBy->full_name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-500">{{ $token->paidBy->designation }}</p>
                        <p class="mt-1 text-xs text-slate-400 dark:text-slate-600">{{ $token->paid_at->format('d M Y, H:i') }}</p>
                    @else
                        <p class="mt-1 text-sm text-slate-400 dark:text-slate-600">Not yet released</p>
                        <p class="text-xs text-slate-500 dark:text-slate-500">Must be somebody other than {{ $token->issuedBy->full_name }}.</p>
                    @endif
                </div>
            </div>

            @if ($token->void_reason)
                <div class="border-t border-slate-200 bg-rose-50 px-6 py-4 dark:border-white/10 dark:bg-rose-500/10">
                    <p class="text-xs font-medium uppercase tracking-wide text-rose-600 dark:text-rose-400">Voided</p>
                    <p class="mt-1 text-sm text-rose-900">{{ $token->void_reason }}</p>
                </div>
            @endif

            <div class="border-t border-slate-200 px-6 py-4 dark:border-white/10">
                <p class="text-[11px] leading-relaxed text-slate-500 dark:text-slate-500">
                    Issued against a bill sighted in person and below the ceiling in force at the time. The issuing and
                    releasing officers are different people, and both names are on permanent record.
                </p>
            </div>
        </div>

        @php
            $tokenSignatures = [
                [
                    'role' => 'Payment Received By (रकम बुझिलिने)',
                    'name' => $token->claimant_name,
                    'designation' => 'Claimant / Payee Signature',
                    'date' => $token->paid_at ? $token->paid_at->format('d M Y') : '_______________',
                ],
                [
                    'role' => 'Token Issued By (जाँच / तयार गर्ने)',
                    'name' => $token->issuedBy->full_name,
                    'designation' => $token->issuedBy->designation,
                    'date' => $token->issued_at->format('d M Y'),
                ],
                [
                    'role' => 'Payment Released By (भुक्तानी दिने)',
                    'name' => $token->paidBy ? $token->paidBy->full_name : 'Pending Release',
                    'designation' => $token->paidBy ? $token->paidBy->designation : 'Accounts / Cashier',
                    'date' => $token->paid_at ? $token->paid_at->format('d M Y') : '_______________',
                ],
            ];
        @endphp

        <x-print-signatures :signatures="$tokenSignatures" />
    </div>
</div>
