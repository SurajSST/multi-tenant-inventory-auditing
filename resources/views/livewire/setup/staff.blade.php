<div>
    <x-page-header title="Staff"
                   subtitle="There is no public registration. Every account is created here, and no login is ever shared — otherwise nothing in the audit trail means anything.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('setup.index') }}" wire:navigate>Setup</x-button>
            <x-button wire:click="newStaff">Add a member of staff</x-button>
        </x-slot:actions>
    </x-page-header>

    @error('staff')
        <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-300">{{ $message }}</div>
    @enderror

    @if ($showForm)
        <x-card class="mb-6" :title="$editingId ? 'Edit account' : 'New account'">
            <form wire:submit="save">
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <x-field label="Staff code" for="staffCode" required :error="$errors->first('staffCode')">
                        <x-input id="staffCode" wire:model="staffCode" placeholder="PSS-011" />
                    </x-field>

                    <x-field label="Full name" for="fullName" required :error="$errors->first('fullName')">
                        <x-input id="fullName" wire:model="fullName" />
                    </x-field>

                    <x-field label="Designation" for="designation" required :error="$errors->first('designation')">
                        <x-input id="designation" wire:model="designation" placeholder="Teacher — Grade 9" />
                    </x-field>

                    <x-field label="Email" for="email" required
                             hint="This is how they sign in." :error="$errors->first('email')">
                        <x-input id="email" type="email" wire:model="email" />
                    </x-field>

                    <x-field label="Phone" for="phone" hint="Optional." :error="$errors->first('phone')">
                        <x-input id="phone" wire:model="phone" />
                    </x-field>

                    <x-field label="Approval tier" for="approvalTier"
                             hint="0 means they are not on the approval chain."
                             :error="$errors->first('approvalTier')">
                        <x-select id="approvalTier" wire:model="approvalTier">
                            <option value="0">Not an approver</option>
                            @foreach ($this->tiers as $tier)
                                <option value="{{ $tier->tier_no }}">
                                    Tier {{ $tier->tier_no }} — {{ $tier->decider_label }} ({{ $tier->range() }})
                                </option>
                            @endforeach
                        </x-select>
                    </x-field>
                </div>

                <div class="mt-6">
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Roles</p>
                    @error('roles') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($allRoles as $role)
                            <label wire:key="role-{{ $role->value }}" class="flex items-start gap-2.5 rounded-lg border border-slate-200 p-3 dark:border-white/10">
                                <input type="checkbox" value="{{ $role->value }}" wire:model="roles"
                                       class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                                <span>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">{{ $role->label() }}</span>
                                    <span class="block text-xs leading-relaxed text-slate-500 dark:text-slate-500">{{ $role->description() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @if (in_array(\App\Enums\Role::AUDITOR->value, $roles, true))
                    <div class="mt-6">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Blocks this auditor may count in</p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-500">Leave every box unticked to allow every block.</p>

                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($this->blocks as $block)
                                <label wire:key="scope-{{ $block->id }}" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 dark:border-white/10 dark:text-slate-300">
                                    <input type="checkbox" value="{{ $block->id }}" wire:model="auditBlocks"
                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                                    {{ $block->name }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                @unless ($editingId)
                    <p class="mt-6 rounded-lg bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:bg-white/5 dark:text-slate-400">
                        The account starts on the default password and the person is made to change it the first time
                        they sign in. Tell them the default in person, not over a message.
                    </p>
                @endunless

                <div class="mt-6 flex gap-2">
                    <x-button type="submit" busy="save">{{ $editingId ? 'Save changes' : 'Create the account' }}</x-button>
                    <x-button variant="secondary" wire:click="cancel">Cancel</x-button>
                </div>
            </form>
        </x-card>
    @endif

    <x-card :flush="true" title="{{ $staff->count() }} account(s)">
        <div class="table-scroll">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-white/10">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5 dark:text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-2.5 font-medium">Person</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Roles</th>
                        <th scope="col" class="px-4 py-2.5 text-center font-medium">Tier</th>
                        <th scope="col" class="px-4 py-2.5 font-medium">Last signed in</th>
                        <th scope="col" class="sticky right-0 z-20 border-l border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-[#111C2E] px-5 py-2.5 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                    @foreach ($staff as $person)
                        <tr class="group {{ $person->is_active ? '' : 'opacity-50' }} hover:bg-slate-50 dark:hover:bg-white/5">
                            <td class="px-5 py-3">
                                <span class="font-medium text-slate-900 dark:text-slate-100">{{ $person->user->full_name }}</span>
                                @unless ($person->is_active)
                                    <x-badge class="ml-1.5">no longer works here</x-badge>
                                @endunless
                                @if ($person->user->must_reset_password)
                                    <x-badge class="ml-1.5 bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">default password</x-badge>
                                @endif
                                <span class="block text-xs text-slate-500 dark:text-slate-500">{{ $person->designation }}</span>
                                <span class="block text-xs text-slate-400 dark:text-slate-600">{{ $person->staff_code }} · {{ $person->user->email }}</span>
                                @if ($person->auditScopes->isNotEmpty())
                                    <span class="block text-xs text-slate-400 dark:text-slate-600">
                                        Counts in: {{ $person->auditScopes->map(fn ($s) => $s->location->name)->join(', ') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($person->roles() as $role)
                                        <x-badge>{{ $role->label() }}</x-badge>
                                    @endforeach
                                </div>
                            </td>
                            <td class="tnum px-4 py-3 text-center text-slate-600 dark:text-slate-400">
                                {{ $person->approval_tier ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-500">
                                {{ $person->user->last_login_at?->format('d M Y, H:i') ?? 'never' }}
                            </td>
                            <td class="sticky right-0 z-10 border-l border-slate-200 bg-white group-hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900 dark:group-hover:bg-[#111C2E] px-5 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-x-3 gap-y-1">
                                    <button type="button" wire:click="edit('{{ $person->id }}')"
                                            class="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-sky-400 dark:hover:text-sky-400">Edit</button>
                                    <button type="button" wire:click="resetPassword('{{ $person->id }}')"
                                            wire:confirm="Reset {{ $person->user->full_name }} to the default password? They will have to change it on their next sign-in."
                                            class="text-xs font-medium text-slate-500 hover:text-slate-900 dark:text-slate-500 dark:hover:text-slate-100">Reset password</button>
                                    @if ($person->user_id !== auth()->id())
                                        <button type="button" wire:click="toggleActive('{{ $person->id }}')"
                                                wire:confirm="{{ $person->is_active
                                                    ? $person->user->full_name.' will no longer be able to work at this school. Everything they have done stays on the record, and their account at any other school is untouched. Continue?'
                                                    : 'Let '.$person->user->full_name.' work at this school again?' }}"
                                                class="text-xs font-medium {{ $person->is_active ? 'text-rose-600 hover:text-rose-500 dark:text-rose-400' : 'text-emerald-600 hover:text-emerald-500 dark:text-emerald-400' }}">
                                            {{ $person->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs leading-relaxed text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-500">
            A posting is stood down, never deleted — everything that person did here stays attributed to them.
            It takes effect on their very next click, not when their session happens to expire. Their account at
            any other school, and their login, are untouched.
        </p>
    </x-card>
</div>
