<div class="mx-auto max-w-7xl space-y-5">
    <x-page-header title="Staff"
                   subtitle="There is no public registration. Every account is created here, and no login is ever shared — otherwise nothing in the audit trail means anything.">
        <x-slot:actions>
            <x-button variant="secondary" href="{{ route('setup.index') }}" wire:navigate>Settings & Roles</x-button>
            <x-button wire:click="newStaff">Add staff member</x-button>
        </x-slot:actions>
    </x-page-header>

    @error('staff')
        <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-300">{{ $message }}</div>
    @enderror

    @if ($showPasswordReset)
        <x-sheet title="Set a temporary password" wireClose="closePasswordReset" maxWidth="max-w-md">
            <p class="mb-5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                Set a temporary sign-in password for <strong class="text-slate-900 dark:text-white">{{ $passwordResetName }}</strong>.
                They must choose a new password at their next sign-in. This changes their login at every school they work at.
            </p>
            <form wire:submit="resetPassword('{{ $passwordResetMembershipId }}')" class="space-y-4">
                <x-field label="Temporary password" for="resetPassword" required hint="Use at least 10 characters." :error="$errors->first('password')">
                    <x-input id="resetPassword" type="password" wire:model="password" autocomplete="new-password" />
                </x-field>
                <x-field label="Confirm temporary password" for="resetPasswordConfirmation" required :error="$errors->first('password_confirmation')">
                    <x-input id="resetPasswordConfirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />
                </x-field>
                <div class="flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end dark:border-white/10">
                    <x-button variant="secondary" wire:click="closePasswordReset">Cancel</x-button>
                    <x-button type="submit" busy="resetPassword">
                        <span wire:loading.remove wire:target="resetPassword">Set temporary password</span>
                        <span wire:loading wire:target="resetPassword">Saving…</span>
                    </x-button>
                </div>
            </form>
        </x-sheet>
    @endif

    <x-field label="Search staff" for="staffSearch" class="mb-5 max-w-md">
        <x-input id="staffSearch" type="search" wire:model.live.debounce.300ms="search" placeholder="Name, staff code, or email" />
    </x-field>

    @if ($showForm)
        <x-sheet :title="$editingId ? 'Edit staff posting' : 'Add staff member'" wireClose="cancel" maxWidth="max-w-4xl">
            <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">Account details and permissions apply to this school only.</p>
            <form id="staff-posting-form" wire:submit="save">
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <x-field label="Staff code" for="staffCode" required :error="$errors->first('staffCode')">
                        <x-input id="staffCode" wire:model="staffCode" placeholder="PSS-011" />
                    </x-field>

                    <x-field label="Full name" for="fullName" required :error="$errors->first('fullName')">
                        <x-input id="fullName" wire:model="fullName" />
                    </x-field>

                    <x-field label="Designation" for="designationSelect" required :error="$errors->first('designation')">
                        <x-select id="designationSelect" wire:model.live="designationSelect">
                            <option value="">Select designation</option>
                            @foreach ($this->standardDesignations as $desig)
                                <option value="{{ $desig }}">{{ $desig }}</option>
                            @endforeach
                            <option value="OTHER">+ Other / Custom designation...</option>
                        </x-select>
                    </x-field>

                    @if ($designationSelect === 'OTHER')
                        <x-field label="Custom Designation" for="customDesignation" required :error="$errors->first('designation')">
                            <x-input id="customDesignation" wire:model.live.debounce.300ms="customDesignation" placeholder="e.g. Sports Coordinator" />
                        </x-field>
                    @endif

                    <x-field label="Email" for="email" required
                             hint="This is how they sign in." :error="$errors->first('email')">
                        <x-input id="email" type="email" wire:model.live.debounce.300ms="email" autocomplete="email" />
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

                @unless ($editingId)
                    @if ($existingPersonNote)
                        <div class="mt-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-relaxed text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200">
                            {{ $existingPersonNote }}
                        </div>
                    @else
                        <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-white/[.03]">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Initial sign-in password</h3>
                            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                Enter a temporary password or leave both fields blank to use the configured default. The new user must change it at first sign-in.
                            </p>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <x-field label="Temporary password" for="password" hint="At least 10 characters." :error="$errors->first('password')">
                                    <x-input id="password" type="password" wire:model="password" autocomplete="new-password" />
                                </x-field>
                                <x-field label="Confirm password" for="password_confirmation" :error="$errors->first('password_confirmation')">
                                    <x-input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" />
                                </x-field>
                            </div>
                        </div>
                    @endif
                @endunless

                <div class="mt-7 border-t border-slate-200 pt-5 dark:border-white/10">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Roles</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Choose the actions this staff member is allowed to perform at this school.</p>
                    @error('roles') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                    <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($allRoles as $role)
                            <label wire:key="role-{{ $role->value }}" class="group flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3.5 transition hover:border-indigo-300 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50/60 dark:border-white/10 dark:bg-slate-900 dark:hover:border-sky-500/40 dark:has-[:checked]:border-sky-500/50 dark:has-[:checked]:bg-sky-500/[.06]">
                                <input type="checkbox" value="{{ $role->value }}" wire:model="roles"
                                       class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-700 dark:text-sky-400" />
                                <span>
                                    <span class="block text-sm font-medium text-slate-900 dark:text-slate-100">{{ $role->label() }}</span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-slate-600 dark:text-slate-400">{{ $role->description() }}</span>
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

            </form>
            <x-slot:footer>
                <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">Permissions and activity are recorded in this school’s audit trail.</p>
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:shrink-0">
                        <x-button type="submit" form="staff-posting-form" busy="save" class="w-full sm:w-auto">{{ $editingId ? 'Save changes' : 'Create account' }}</x-button>
                        <x-button variant="secondary" wire:click="cancel" class="w-full sm:w-auto">Cancel</x-button>
                    </div>
                </div>
            </x-slot:footer>
        </x-sheet>
    @endif

    <div class="mb-4 flex items-center justify-between gap-4">
        <div class="w-full max-w-sm">
            <x-input type="search" wire:model.live.debounce.300ms="search" placeholder="Search staff name, code, designation, email..." />
        </div>
    </div>

    <x-card :flush="true" title="{{ $staff->total() }} account(s)">
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
                                    <x-badge class="ml-1.5 bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300">password change required</x-badge>
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
                                    <button type="button" wire:click="beginPasswordReset('{{ $person->id }}')"
                                            class="text-xs font-medium text-slate-500 hover:text-slate-900 dark:text-slate-500 dark:hover:text-slate-100">Reset password</button>
                                    @if ($person->user_id !== auth()->id())
                                        <x-confirm-dialog action="toggleActive" :params="[$person->id]"
                                                          :title="$person->is_active ? 'Deactivate this staff member?' : 'Reactivate this staff member?'"
                                                          :message="$person->is_active
                                                            ? $person->user->full_name.' will no longer be able to work at this school. Their history stays on the record, and their account at other schools is untouched.'
                                                            : 'Let '.$person->user->full_name.' work at this school again?'"
                                                          :confirm-label="$person->is_active ? 'Deactivate staff' : 'Reactivate staff'"
                                                          :trigger-class="'text-xs font-medium '.($person->is_active ? 'text-rose-600 hover:text-rose-500 dark:text-rose-400' : 'text-emerald-600 hover:text-emerald-500 dark:text-emerald-400')">
                                            {{ $person->is_active ? 'Deactivate' : 'Reactivate' }}
                                        </x-confirm-dialog>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-3 dark:border-white/10">{{ $staff->links() }}</div>

        <p class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs leading-relaxed text-slate-500 dark:border-white/10 dark:bg-white/5 dark:text-slate-500">
            A posting is stood down, never deleted — everything that person did here stays attributed to them.
            It takes effect on their very next click, not when their session happens to expire. Their account at
            any other school, and their login, are untouched.
        </p>
    </x-card>
</div>
