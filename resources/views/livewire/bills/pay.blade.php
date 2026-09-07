<div>
    <x-page-header title="Record Supplier Payment"
                   subtitle="Disburse funds against approved and matched vendor bills. Posts double-entry disbursement journal." />

    <x-errors />

    <form wire:submit="save" class="space-y-6">
        <x-card title="Disbursement Details">
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <x-field label="Vendor / Supplier" for="vendorId" required :error="$errors->first('vendorId')">
                    <x-select id="vendorId" wire:model.live="vendorId">
                        <option value="">Select a vendor</option>
                        @foreach ($this->vendors as $v)
                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field label="Payment Method" for="paymentMethod" required :error="$errors->first('paymentMethod')">
                    <x-select id="paymentMethod" wire:model="paymentMethod">
                        @foreach (\App\Enums\PaymentMethod::cases() as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </x-select>
                </x-field>

                <x-field label="Payment Date" for="paymentDate" required :error="$errors->first('paymentDate')">
                    <x-input id="paymentDate" type="date" wire:model="paymentDate" />
                </x-field>

                <x-field label="Payment Amount (NPR)" for="amount" required :error="$errors->first('amount')">
                    <x-input id="amount" type="number" step="0.01" min="0" wire:model="amount"
                             wire:keydown.enter.prevent="autoAllocate"
                             placeholder="0.00" />
                </x-field>

                <x-field label="Cheque / Transaction Ref" for="referenceNo" hint="Optional." :error="$errors->first('referenceNo')">
                    <x-input id="referenceNo" wire:model="referenceNo" placeholder="E.g. CHQ-98124 or NCHL-881" />
                </x-field>

                <x-field label="Bank Name" for="bankName" hint="Optional." :error="$errors->first('bankName')">
                    <x-input id="bankName" wire:model="bankName" placeholder="E.g. Nepal Bank Limited" />
                </x-field>

                <div class="sm:col-span-2 lg:col-span-3">
                    <x-field label="Remarks" for="remarks" hint="Optional." :error="$errors->first('remarks')">
                        <x-textarea id="remarks" wire:model="remarks" rows="2" placeholder="Payment voucher notes or reference..." />
                    </x-field>
                </div>
            </div>
        </x-card>

        @if ($this->vendorId)
            <x-card title="Bill Allocations"
                    subtitle="Allocate the payment against outstanding bills for this vendor."
                    :flush="true">
                @if ($this->payableBills->isEmpty())
                    <div class="p-5 text-sm text-slate-500 dark:text-slate-400">
                        No outstanding matched bills found for this vendor.
                    </div>
                @else
                    <div class="p-4 bg-slate-50 border-b border-slate-200 dark:bg-white/5 dark:border-white/10 flex justify-between items-center">
                        <span class="text-xs text-slate-500">Enter allocations manually or click Auto-Allocate.</span>
                        <x-button type="button" variant="secondary" wire:click="autoAllocate">Auto-Allocate Amount</x-button>
                    </div>
                    <div class="table-scroll">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                                <tr>
                                    <th scope="col" class="px-5 py-2.5 font-medium">Bill No</th>
                                    <th scope="col" class="px-4 py-2.5 font-medium">Bill Date</th>
                                    <th scope="col" class="px-4 py-2.5 text-right font-medium">Bill Total</th>
                                    <th scope="col" class="px-4 py-2.5 text-right font-medium">Already Paid</th>
                                    <th scope="col" class="px-4 py-2.5 text-right font-medium">Remaining</th>
                                    <th scope="col" class="px-4 py-2.5 text-right font-medium">Allocate Now</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                @foreach ($this->payableBills as $bill)
                                    <tr wire:key="b-{{ $bill->id }}">
                                        <td class="px-5 py-2.5 font-medium text-slate-900 dark:text-slate-100">
                                            {{ $bill->bill_no }}
                                            @if ($bill->purchaseOrder)
                                                <span class="block text-xs text-slate-500">{{ $bill->purchaseOrder->ref }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-slate-600 dark:text-slate-400">{{ $bill->bill_date->format('d M Y') }}</td>
                                        <td class="px-4 py-2.5 text-right">
                                            <x-money :amount="$bill->bill_amount" :bare="true" />
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-slate-500">
                                            <x-money :amount="$bill->paid_amount" :bare="true" />
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-semibold text-rose-700 dark:text-rose-400">
                                            <x-money :amount="$bill->remainingBalance()" :bare="true" />
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <input type="number" step="0.01" min="0" max="{{ $bill->remainingBalance() }}"
                                                   wire:model="allocations.{{ $bill->id }}"
                                                   class="tnum w-32 rounded-lg border-slate-300 bg-white text-right text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-sky-500 dark:focus:ring-sky-500"
                                                   placeholder="0.00" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <x-slot:footer>
                    <div class="flex items-center justify-between">
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            Disbursement recorded by {{ auth()->user()->full_name }}
                        </div>
                        <div class="flex items-center gap-3">
                            <x-button variant="secondary" href="{{ route('bills.index') }}" wire:navigate>Cancel</x-button>
                            <x-button type="submit">Record Payment Voucher</x-button>
                        </div>
                    </div>
                </x-slot:footer>
            </x-card>
        @endif
    </form>
</div>
