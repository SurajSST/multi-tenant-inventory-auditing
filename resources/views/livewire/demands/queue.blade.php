<div>
    <x-page-header title="My Approvals"
                   subtitle="Only what genuinely sits at tier {{ auth()->user()->approval_tier }} — your band. Requests you raised yourself never appear here; you cannot decide on your own." />

    @error('decision')
        <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-300">{{ $message }}</div>
    @enderror

    @if (! auth()->user()->approval_tier)
        <x-card>
            <x-empty title="You are not on the approval chain"
                     note="The Super Admin sets which tier each approver decides at, under Setup → Staff." />
        </x-card>
    @elseif ($queue->isEmpty())
        <x-card>
            <x-empty title="Nothing is waiting on you"
                     note="When a demand form reaches your band it appears here, and nothing moves until you decide." />
        </x-card>
    @else
        @if ($queue->count() > 1)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-xs dark:border-white/10 dark:bg-slate-900">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
                    <input type="checkbox" wire:model.live="selectAll"
                           class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                    Select all {{ $queue->count() }} demands
                </label>

                @if (! empty($selected))
                    <div class="flex items-center gap-2.5">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ count($selected) }} selected</span>
                        <x-button wire:click="openBulkModal" class="!py-1.5 !text-xs">
                            Approve Selected ({{ count($selected) }})
                        </x-button>
                    </div>
                @endif
            </div>
        @endif

        <div class="space-y-5">
            @foreach ($queue as $demand)
                <x-card wire:key="q-{{ $demand->id }}" :flush="true">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-white/10">
                        <div class="flex items-start gap-3 min-w-0">
                            @if ($queue->count() > 1)
                                <input type="checkbox" value="{{ $demand->id }}" wire:model.live="selected"
                                       class="mt-1 size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                            @endif
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('demands.show', $demand) }}" wire:navigate
                                       class="font-semibold text-slate-900 hover:text-indigo-600 dark:text-slate-100 dark:hover:text-sky-400">{{ $demand->ref }}</a>
                                    <x-badge>tier {{ $demand->current_tier }} of {{ $demand->final_tier }}</x-badge>
                                </div>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    {{ $demand->department }} · raised by {{ $demand->raisedBy->full_name }},
                                    {{ $demand->raisedBy->designation }} · {{ $demand->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                        <x-money :amount="$demand->total_amount" class="text-lg font-semibold text-slate-900 dark:text-slate-100" />
                    </div>

                    <div class="px-5 py-4">
                        <p class="text-sm leading-relaxed text-slate-700 dark:text-slate-300">{{ $demand->justification }}</p>

                        <div class="table-scroll mt-4 rounded-lg border border-slate-200 dark:border-white/10">
                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                                    <tr>
                                        <th scope="col" class="px-4 py-2 font-medium">Item</th>
                                        <th scope="col" class="px-4 py-2 text-right font-medium">Qty</th>
                                        <th scope="col" class="px-4 py-2 text-right font-medium">Rate</th>
                                        <th scope="col" class="px-4 py-2 text-right font-medium">Line total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                    @foreach ($demand->lines as $line)
                                        <tr>
                                            <td class="px-4 py-2 text-slate-900 dark:text-slate-100">
                                                {{ $line->item_name }}
                                                @if ($line->isNewItem())
                                                    <x-badge class="ml-1 bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">not on the register</x-badge>
                                                @endif
                                                @if ($line->specification)
                                                    <span class="block text-xs text-slate-500 dark:text-slate-500">{{ $line->specification }}</span>
                                                @endif
                                            </td>
                                            <td class="tnum px-4 py-2 text-right text-slate-600 dark:text-slate-400">{{ $line->quantity }}</td>
                                            <td class="px-4 py-2 text-right"><x-money :amount="$line->unit_rate" :bare="true" class="text-slate-600 dark:text-slate-400" /></td>
                                            <td class="px-4 py-2 text-right"><x-money :amount="$line->line_total" :bare="true" class="font-medium text-slate-900 dark:text-slate-100" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($demand->approvals->isNotEmpty())
                            <div class="mt-4 rounded-lg bg-slate-50 px-4 py-3 dark:bg-white/5">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-500">Already signed</p>
                                <ul class="mt-1.5 space-y-1 text-sm text-slate-600 dark:text-slate-400">
                                    @foreach ($demand->approvals as $approval)
                                        <li>
                                            Tier {{ $approval->tier_no }} — {{ $approval->actor->full_name }},
                                            {{ $approval->acted_at->format('d M Y H:i') }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 dark:border-white/10 dark:bg-white/5">
                        <x-button variant="danger" wire:click="open('{{ $demand->id }}', 'REJECT')">Reject</x-button>
                        <x-button wire:click="open('{{ $demand->id }}', 'APPROVE')">Approve</x-button>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    {{-- Decision panel --}}
    @if ($deciding)
        <x-sheet :title="($action === 'APPROVE' ? 'Approve ' : 'Reject ') . $deciding->ref" wireClose="close">
            <x-slot:footer>
                <x-button variant="secondary" wire:click="close">Cancel</x-button>
                <x-button :variant="$action === 'REJECT' ? 'danger' : 'primary'" wire:click="confirm" wire:loading.attr="disabled">
                    Confirm {{ $action === 'APPROVE' ? 'approval' : 'rejection' }}
                </x-button>
            </x-slot:footer>

            <p class="text-sm text-slate-500 dark:text-slate-400">
                <x-money :amount="$deciding->total_amount" class="font-semibold text-slate-900 dark:text-slate-100" /> ·
                raised by {{ $deciding->raisedBy->full_name }}
            </p>

            <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs leading-relaxed text-slate-600 dark:bg-white/5 dark:text-slate-400">
                This decision is recorded against your name and timestamped. It cannot be edited or deleted
                afterwards — a change of mind has to be a new, separate entry.
            </p>

            <div class="mt-5 space-y-4">
                @if ($action === 'REJECT')
                    <x-field label="Reason for rejection" for="reason" required
                             hint="The person who raised it will see this. At least five characters."
                             :error="$errors->first('reason')">
                        <x-textarea id="reason" wire:model="reason" rows="3" />
                    </x-field>
                @else
                    @if ($tiers->firstWhere('tier_no', $deciding->current_tier)?->requires_minute)
                        <x-field label="Committee minute reference" for="minuteRef" required
                                 hint="This band is decided by the committee, so the minute must be on record."
                                 :error="$errors->first('minute_ref')">
                            <x-input id="minuteRef" wire:model="minuteRef" placeholder="e.g. Minute 2082/14" />
                        </x-field>
                    @endif

                    <x-field label="Note" for="reason" hint="Optional — anything the trail should carry.">
                        <x-textarea id="reason" wire:model="reason" rows="2" />
                    </x-field>
                @endif
            </div>
        </x-sheet>
    @endif

    {{-- Bulk decision panel --}}
    @if ($bulkModal)
        <x-sheet :title="'Bulk Approve ' . count($selected) . ' Demand Forms'" wireClose="closeBulkModal">
            <x-slot:footer>
                <x-button variant="secondary" wire:click="closeBulkModal">Cancel</x-button>
                <x-button wire:click="approveSelected">Sign and approve all</x-button>
            </x-slot:footer>

            <p class="text-sm text-slate-600 dark:text-slate-300">
                You are about to approve <strong>{{ count($selected) }}</strong> demand forms at <strong>Tier {{ auth()->user()->approval_tier }}</strong>.
            </p>

            <p class="mt-3 rounded-lg bg-amber-50 px-3.5 py-2.5 text-xs leading-relaxed text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                Each approval will be individually recorded against your name in the immutable audit trail. Any form that requires a subsequent higher tier will advance automatically.
            </p>

            <div class="mt-5 space-y-4">
                <x-field label="Meeting or minute reference (optional)" for="bulkMinuteRef"
                         hint="If deciding under a committee or board resolution.">
                    <x-input id="bulkMinuteRef" wire:model="bulkMinuteRef" placeholder="e.g. Committee Minute 2082/15" />
                </x-field>
            </div>
        </x-sheet>
    @endif
</div>
