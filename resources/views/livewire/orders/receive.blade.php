<div>
    <x-page-header :title="'Verify ' . $this->order->ref"
                   :subtitle="$this->order->vendor->name . ' · ordered by ' . $this->order->orderedBy->full_name . ' on ' . $this->order->ordered_at->format('d M Y')" />

    <x-errors />

    @error('receipt')
        <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-300">{{ $message }}</div>
    @enderror

    @if ($this->order->receipt)
        <x-card>
            <x-empty title="This order has already been verified"
                     :note="$this->order->receipt->receivedBy->full_name . ' verified it on ' . $this->order->receipt->received_at->format('d M Y, H:i') . '. A receipt is recorded once and cannot be redone.'">
                <x-button variant="secondary" href="{{ route('orders.show', $this->order) }}" wire:navigate>See the order</x-button>
            </x-empty>
        </x-card>
    @elseif ($this->order->ordered_by_id === auth()->id())
        <x-card>
            <x-empty title="You placed this order"
                     note="Somebody else has to verify that it arrived. That separation is the whole point of the control, and the database enforces it even if this screen were bypassed.">
                <x-button variant="secondary" href="{{ route('orders.index') }}" wire:navigate>Back to orders</x-button>
            </x-empty>
        </x-card>
    @else
        <form wire:submit="save" class="space-y-6">

            <x-card title="What arrived"
                    subtitle="Check each line against what was ordered. Type the real figure — a short delivery recorded honestly is what makes the register worth reading."
                    :flush="true">
                <div class="table-scroll">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-medium">Item</th>
                                <th scope="col" class="px-4 py-2.5 text-right font-medium">Ordered</th>
                                <th scope="col" class="px-4 py-2.5 text-right font-medium">Received</th>
                                <th scope="col" class="px-5 py-2.5 font-medium">Remark</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                            @foreach ($this->order->demand->lines as $line)
                                @php $short = (int) ($received[$line->id] ?? 0) < $line->quantity @endphp
                                <tr wire:key="rl-{{ $line->id }}" class="{{ $short ? 'bg-amber-50 dark:bg-amber-500/10' : '' }}">
                                    <td class="px-5 py-2.5 text-slate-900 dark:text-slate-100">
                                        {{ $line->item_name }}
                                        @if (! $line->item_type_id)
                                            <span class="block text-xs text-amber-700 dark:text-amber-400">Not on the register — this will not post into stock.</span>
                                        @elseif ($line->specification)
                                            <span class="block text-xs text-slate-500 dark:text-slate-500">{{ $line->specification }}</span>
                                        @endif
                                    </td>
                                    <td class="tnum px-4 py-2.5 text-right text-slate-600 dark:text-slate-400">{{ $line->quantity }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <input type="number" min="0" max="{{ $line->quantity }}" inputmode="numeric"
                                               wire:model.live.debounce.400ms="received.{{ $line->id }}"
                                               class="tnum w-24 rounded-lg border-slate-300 bg-white text-right text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500 {{ $short ? 'border-amber-400 dark:border-amber-500/60' : '' }}" />
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <x-input wire:model="remarks.{{ $line->id }}" placeholder="Optional" class="text-xs" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card title="The delivery">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field label="Which block did it go into?" for="locationId" required
                             hint="The received quantities post straight into this block's stock ledger."
                             :error="$errors->first('locationId')">
                        <x-select id="locationId" wire:model="locationId">
                            @foreach ($this->blocks as $block)
                                <option value="{{ $block->id }}">{{ $block->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Condition" for="condition" required :error="$errors->first('condition')">
                        <x-select id="condition" wire:model="condition">
                            @foreach (\App\Enums\ReceiptCondition::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Challan / gate pass number" for="challanNo" hint="Optional." :error="$errors->first('challanNo')">
                        <x-input id="challanNo" wire:model="challanNo" />
                    </x-field>

                    <x-field label="Photo of the delivery" for="photo"
                             hint="Optional. Useful when something arrived damaged or wrong."
                             :error="$errors->first('photo')">
                        <input id="photo" type="file" wire:model="photo" accept="image/*"
                               class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:text-slate-400 dark:file:bg-white/10 dark:file:text-slate-300 dark:hover:file:bg-white/20" />
                        <div wire:loading wire:target="photo" class="mt-1 text-xs text-slate-500 dark:text-slate-500">Uploading…</div>
                    </x-field>

                    <x-field label="Discrepancy note" for="discrepancyNote" class="sm:col-span-2"
                             :required="$this->isShort"
                             :hint="$this->isShort
                                ? 'Less arrived than was ordered, so this is required.'
                                : 'Optional — anything that did not match the order.'"
                             :error="$errors->first('discrepancyNote')">
                        <x-textarea id="discrepancyNote" wire:model="discrepancyNote" rows="3" />
                    </x-field>
                </div>

                <p class="mt-5 rounded-lg bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:bg-white/5 dark:text-slate-400">
                    You are verifying this as <strong>{{ auth()->user()->full_name }}</strong>.
                    {{ $this->order->orderedBy->full_name }} placed the order and cannot do this step.
                    The receipt is recorded once, timestamped, and cannot be edited afterwards.
                </p>
            </x-card>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <x-button variant="secondary" href="{{ route('orders.index') }}" wire:navigate>Cancel</x-button>
                <x-button type="submit" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">Confirm the goods arrived</span>
                    <span wire:loading wire:target="save">Recording…</span>
                </x-button>
            </div>
        </form>
    @endif
</div>
