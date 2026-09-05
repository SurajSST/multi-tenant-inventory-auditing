<div>
    <x-page-header title="Petty Cash"
                   subtitle="Bill sighted → token generated → routed to Accounts → paid. Whoever issues a token can never be the one who releases the payment.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('export.petty-cash') }}">Export</x-button>
            <x-button href="{{ route('petty-cash.issue') }}" wire:navigate>Issue a token</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-stat label="Ceiling per bill" :value="\App\Support\Money::npr($summary['ceiling_per_bill'])"
                note="anything above goes through a demand form" />
        <x-stat label="Awaiting payment" :value="$summary['awaiting_payment']"
                :tone="$summary['awaiting_payment'] ? 'amber' : 'slate'" note="tokens not yet settled" />
        <x-stat label="Value outstanding" :value="\App\Support\Money::npr($summary['awaiting_payment_value'])" />
        <x-stat label="Issued this month" :value="\App\Support\Money::npr($summary['issued_this_month'])"
                note="rolling exposure of the float" />
    </div>

    {{-- Monthly Disbursements Chart & Float Rules --}}
    <div class="mb-6 grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-card title="Monthly Disbursements" subtitle="Settled petty cash reimbursements by month">
                <x-slot:actions>
                    <x-badge>FY {{ \App\Support\FiscalYear::label() }}</x-badge>
                </x-slot:actions>
                <x-charts.petty-cash-bar :data="$monthlySpend" />
            </x-card>
        </div>
        <div>
            <x-card title="Float Guidelines" subtitle="Controls active on every token">
                <div class="space-y-3 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                    <div class="flex items-start gap-2.5">
                        <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 font-bold text-[10px]">1</span>
                        <p><strong>Ceiling per bill:</strong> <x-money :amount="$summary['ceiling_per_bill']" class="font-bold text-slate-900 dark:text-white" />. Anything above must go through a formal demand form.</p>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400 font-bold text-[10px]">2</span>
                        <p><strong>Two-person rule:</strong> Whoever issues a token cannot be the one who releases the payment in Accounts.</p>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 font-bold text-[10px]">3</span>
                        <p><strong>Anti-double claim:</strong> A sighted bill cannot be claimed twice or entered into both petty cash and main orders.</p>
                    </div>
                </div>
            </x-card>
        </div>
    </div>

    <x-card class="mb-5">
        <x-field label="Status" for="status" class="max-w-xs">
            <x-select id="status" wire:model.live="status">
                <option value="">Every token</option>
                @foreach (\App\Enums\TokenStatus::cases() as $case)
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
                @endforeach
            </x-select>
        </x-field>
    </x-card>

    <x-card :flush="true" title="{{ $tokens->total() }} token{{ $tokens->total() === 1 ? '' : 's' }}">
        @if ($tokens->isEmpty())
            <x-empty title="No tokens yet"
                     note="A token is generated against a sighted bill under the ceiling, and is settled by a second person in Accounts.">
                <x-button href="{{ route('petty-cash.issue') }}" wire:navigate>Issue a token</x-button>
            </x-empty>
        @else
            <div class="table-scroll">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 font-medium">Token</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Bill / vendor</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Claimant</th>
                            <th scope="col" class="px-4 py-2.5 text-right font-medium">Amount</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Status</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Issued / paid by</th>
                            <th scope="col" class="sticky right-0 z-20 border-l border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-[#111C2E] px-5 py-2.5 text-right font-medium no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 transition-opacity duration-150 dark:divide-white/5" wire:loading.class="opacity-50 pointer-events-none">
                        @foreach ($tokens as $token)
                            <tr wire:key="token-{{ $token->id }}" class="group hover:bg-slate-50 dark:hover:bg-white/5">
                                <td class="px-5 py-2.5">
                                    <a href="{{ route('petty-cash.show', $token) }}" wire:navigate
                                       class="font-medium text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-sky-400">{{ $token->serial }}</a>
                                    <span class="block text-xs text-slate-400 dark:text-slate-600">{{ $token->issued_at->format('d M Y') }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="text-slate-900 dark:text-slate-100">{{ $token->bill_no }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-500">{{ $token->vendor_name }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="text-slate-700 dark:text-slate-300">{{ $token->claimant_name }}</span>
                                    <span class="block text-xs text-slate-500 dark:text-slate-500">{{ Str::limit($token->purpose, 40) }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <x-money :amount="$token->amount" :bare="true" class="font-semibold text-slate-900 dark:text-slate-100" />
                                </td>
                                <td class="px-4 py-2.5">
                                    <x-badge :class="$token->status->badge()">{{ $token->status->label() }}</x-badge>
                                    @if ($token->void_reason)
                                        <span class="mt-1 block max-w-xs text-xs text-slate-500 dark:text-slate-500">{{ $token->void_reason }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-slate-500 dark:text-slate-500">
                                    {{ $token->issuedBy->full_name }}
                                    @if ($token->paidBy)
                                        <span class="block text-slate-400 dark:text-slate-600">paid by {{ $token->paidBy->full_name }}</span>
                                    @endif
                                </td>
                                <td class="sticky right-0 z-10 border-l border-slate-200 bg-white group-hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:group-hover:bg-[#111C2E] px-5 py-2.5 text-right no-print">
                                    @if ($token->isOpen())
                                        <div class="flex flex-wrap justify-end gap-1.5">
                                            @if ($token->status === \App\Enums\TokenStatus::ISSUED)
                                                <button type="button" wire:click="sendToAccounts('{{ $token->id }}')"
                                                        class="text-xs font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">Send to Accounts</button>
                                            @endif

                                            @if ($token->issued_by_id === auth()->id())
                                                <span class="text-xs text-slate-400 dark:text-slate-600" title="You issued this token">
                                                    you issued it
                                                </span>
                                            @else
                                                <button type="button" wire:click="pay('{{ $token->id }}')"
                                                        wire:confirm="Release {{ \App\Support\Money::npr($token->amount) }} to {{ $token->claimant_name }}?"
                                                        class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-400">Mark paid</button>
                                            @endif

                                            <button type="button" wire:click="openVoid('{{ $token->id }}')"
                                                    class="text-xs font-medium text-rose-600 hover:text-rose-500 dark:text-rose-400">Void</button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($tokens->hasPages())
            <div class="border-t border-slate-100 px-5 py-3 dark:border-white/5">{{ $tokens->links() }}</div>
        @endif
    </x-card>

    @if ($voiding)
        <x-sheet :title="'Void ' . $voiding->serial" wireClose="closeVoid">
            <x-slot:footer>
                <x-button variant="secondary" wire:click="closeVoid">Cancel</x-button>
                <x-button variant="danger" wire:click="void">Void this token</x-button>
            </x-slot:footer>

            <p class="text-sm text-slate-500 dark:text-slate-400">
                <x-money :amount="$voiding->amount" class="font-semibold text-slate-900 dark:text-slate-100" /> · {{ $voiding->claimant_name }}
            </p>
            <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-600 dark:bg-white/5 dark:text-slate-400">
                The token stays on record as voided, with your name and reason attached. A token that has already
                been paid cannot be voided at all.
            </p>

            <div class="mt-5">
                <x-field label="Reason" for="voidReason" required :error="$errors->first('reason')">
                    <x-textarea id="voidReason" wire:model="voidReason" rows="3" />
                </x-field>
            </div>
        </x-sheet>
    @endif
</div>
