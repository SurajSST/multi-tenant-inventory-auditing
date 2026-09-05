<?php

namespace App\Livewire\Setup;

use App\Enums\Role;
use App\Models\Location;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SettingService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Staff at this school.
 *
 * What is edited here is a POSTING, not a person. Somebody may already have an
 * account because they work at another school — in that case they are attached
 * by their email address and keep the one login, while their staff code,
 * designation, roles and approval tier here are entirely this school's own.
 *
 * Nothing on this screen can reach another school's records.
 */
class Staff extends Component
{
    public bool $showForm = false;

    /** The posting being edited, never the person. */
    public ?string $editingId = null;

    public string $staffCode = '';

    public string $fullName = '';

    public string $designation = '';

    public string $designationSelect = '';

    public string $customDesignation = '';

    public string $email = '';

    public string $phone = '';

    public array $roles = [];

    public int $approvalTier = 0;

    /** Blocks an auditor may count in. Empty means every block. */
    public array $auditBlocks = [];

    /** Set when the email entered already belongs to somebody. */
    public ?string $existingPersonNote = null;

    #[Computed]
    public function standardDesignations(): array
    {
        $defaults = [
            'Managing Director',
            'Chairman',
            'Principal',
            'Vice Principal',
            'Administrative Officer',
            'Head of Department — Science',
            'Head of Department — Mathematics',
            'Head of Department — English',
            'Head of Department — Nepali',
            'Head of Department — Social Studies',
            'Head of Department — Computer / IT',
            'Teacher — Grade 8',
            'Teacher — Grade 9',
            'Teacher — Grade 10',
            'Teacher — Primary',
            'Teacher — Secondary',
            'Store / Purchase Officer',
            'Store Keeper (Receiving)',
            'Accounts Officer',
            'Accounts Assistant',
            'Assigned Stock Auditor',
            'Lab Assistant',
            'Librarian',
        ];

        try {
            $existing = TenantUser::distinct()->pluck('designation')->filter()->values()->all();
        } catch (\Throwable) {
            $existing = [];
        }

        return collect(array_merge($defaults, $existing))
            ->map(fn ($d) => trim($d))
            ->unique()
            ->values()
            ->all();
    }

    public function updatedDesignationSelect(string $val): void
    {
        if ($val === 'OTHER') {
            $this->designation = trim($this->customDesignation);
        } else {
            $this->designation = $val;
        }
    }

    public function updatedCustomDesignation(string $val): void
    {
        if ($this->designationSelect === 'OTHER') {
            $this->designation = trim($val);
        }
    }

    #[Computed]
    public function blocks(): Collection
    {
        return Location::active()->orderBy('name')->get();
    }

    #[Computed]
    public function tiers(): Collection
    {
        return app(SettingService::class)->tiers();
    }

    /**
     * A posting at THIS school, or a 404.
     *
     * tenant_users deliberately carries no global scope — signing in has to
     * read it before any school is active — so every lookup driven by an id
     * from the browser has to say which school it means. Without this, a
     * school's own Super Admin could pass a membership id belonging to a
     * different school and reset that person's password or end their posting:
     * the route middleware admits them to this screen, and Livewire's checksum
     * covers the component's own state, not the arguments of a method call.
     */
    private function posting(string $membershipId, array $with = []): TenantUser
    {
        return TenantUser::query()
            ->where('tenant_id', app(TenantContext::class)->idOrFail())
            ->when($with, fn ($q) => $q->with($with))
            ->findOrFail($membershipId);
    }

    public function newStaff(): void
    {
        $this->reset([
            'editingId', 'staffCode', 'fullName', 'designation', 'email',
            'phone', 'roles', 'approvalTier', 'auditBlocks', 'existingPersonNote',
        ]);

        $this->roles = [Role::INITIATOR->value];
        $this->showForm = true;
        $this->resetErrorBag();
    }

    /**
     * Typing an email that already exists means this person works at another
     * school. Say so, so it is obvious they are being attached rather than
     * created, and that their name and password stay as they already are.
     */
    public function updatedEmail(): void
    {
        $this->existingPersonNote = null;

        if ($this->editingId || ! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $person = User::where('email', $this->email)->first();

        if (! $person) {
            return;
        }

        $this->fullName = $person->full_name;
        $this->phone = (string) $person->phone;
        $this->existingPersonNote = $person->full_name.' already has an account here. '.
            'Adding them will give them a posting at this school; they keep their existing password.';
    }

    public function edit(string $membershipId): void
    {
        $membership = $this->posting($membershipId, ['user', 'roleRows', 'auditScopes']);

        $this->editingId = $membership->id;
        $this->staffCode = $membership->staff_code;
        $this->fullName = $membership->user->full_name;
        $this->designation = $membership->designation;
        if (in_array($this->designation, $this->standardDesignations, true)) {
            $this->designationSelect = $this->designation;
            $this->customDesignation = '';
        } else {
            $this->designationSelect = 'OTHER';
            $this->customDesignation = $this->designation;
        }

        $this->email = $membership->user->email;
        $this->phone = (string) $membership->user->phone;
        $this->roles = $membership->roles()->map(fn (Role $r) => $r->value)->all();
        $this->approvalTier = $membership->approval_tier;
        $this->auditBlocks = $membership->auditScopes->pluck('location_id')->all();
        $this->existingPersonNote = null;
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset([
            'editingId', 'showForm', 'staffCode', 'fullName', 'designation',
            'designationSelect', 'customDesignation',
            'email', 'phone', 'roles', 'approvalTier', 'auditBlocks', 'existingPersonNote',
        ]);
    }

    public function save(AuditLogger $audit): void
    {
        if ($this->designationSelect === 'OTHER') {
            $this->designation = trim($this->customDesignation);
        } elseif ($this->designationSelect !== '') {
            $this->designation = trim($this->designationSelect);
        }

        $tenant = app(TenantContext::class)->current();
        $membership = $this->editingId ? $this->posting($this->editingId) : null;

        $this->validate([
            // Staff codes are this school's own; another school may use the same one.
            'staffCode' => ['required', 'string', 'max:40', Rule::unique('tenant_users', 'staff_code')
                ->where('tenant_id', $tenant->id)
                ->ignore($membership?->id)],
            'fullName' => ['required', 'string', 'max:120'],
            'designation' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(Role::class)],
            'approvalTier' => ['required', 'integer', 'min:0', 'max:20'],
            'auditBlocks' => ['array'],
            'auditBlocks.*' => ['string', Rule::exists('locations', 'id')->where('tenant_id', $tenant->id)],
        ], [
            'roles.required' => 'Give this person at least one role, or they cannot do anything.',
            'staffCode.unique' => 'That staff code is already used at this school.',
        ]);

        // Nobody may sign this school out of its own setup screens.
        if ($membership
            && $membership->user_id === auth()->id()
            && ! in_array(Role::SUPER_ADMIN->value, $this->roles, true)
            && auth()->user()->isSuperAdmin()) {
            $this->addError('roles', 'You cannot take the Super Admin role off your own posting here — nobody would be left who can put it back.');

            return;
        }

        $isNewPosting = ! $membership;

        DB::transaction(function () use (&$membership, $tenant, $audit, $isNewPosting) {
            $person = $membership?->user ?? User::where('email', $this->email)->first();
            $isNewPerson = ! $person;

            if ($isNewPerson) {
                // A new account starts on the shared default and is forced to
                // change it the first time the person signs in.
                $person = User::create([
                    'full_name' => $this->fullName,
                    'email' => $this->email,
                    'phone' => $this->phone ?: null,
                    'password' => Hash::make(config('prativa.seed_password')),
                    'is_active' => true,
                    'must_reset_password' => false,
                ]);
            } else {
                // Name and phone belong to the person, so they are shared across
                // every school they work at — changing them here changes them
                // everywhere, which is correct for a name and a phone number.
                $person->update([
                    'full_name' => $this->fullName,
                    'phone' => $this->phone ?: null,
                ]);
            }

            $membership = TenantUser::updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $person->id],
                [
                    'staff_code' => $this->staffCode,
                    'designation' => $this->designation,
                    'approval_tier' => $this->approvalTier,
                    'is_active' => true,
                ],
            );

            $membership->syncRoles($this->roles);

            // If somebody just edited their own posting, the roles cached on
            // the signed-in model are now a page out of date.
            if ($person->id === auth()->id()) {
                auth()->user()->forgetMemberships();
            }

            $membership->auditScopes()
                ->whereNotIn('location_id', $this->auditBlocks ?: ['-'])
                ->delete();

            foreach ($this->auditBlocks as $locationId) {
                $membership->auditScopes()->firstOrCreate(
                    ['location_id' => $locationId],
                    ['tenant_id' => $tenant->id],
                );
            }

            $audit->record(
                action: $isNewPosting ? 'STAFF_ADDED' : 'STAFF_UPDATED',
                entity: 'tenant_users',
                entityId: $membership->id,
                detail: ($isNewPosting ? 'Posted to this school: ' : 'Posting updated: ')
                    .$person->full_name.', '.$this->designation
                    .' — roles: '.collect($this->roles)->join(', ')
                    .($this->approvalTier ? '; decides at tier '.$this->approvalTier : '')
                    .($isNewPerson ? '' : ' (existing account)'),
            );
        });

        session()->flash('status', $isNewPosting
            ? $this->fullName.' can now work at this school.'
            : $this->fullName.' updated.');

        $this->cancel();
    }

    /** Ends somebody's posting here without touching their account elsewhere. */
    public function toggleActive(string $membershipId, AuditLogger $audit): void
    {
        $membership = $this->posting($membershipId, ['user']);

        if ($membership->user_id === auth()->id()) {
            $this->addError('staff', 'You cannot stand yourself down from your own school.');

            return;
        }

        $membership->update(['is_active' => ! $membership->is_active]);

        $audit->record(
            action: $membership->is_active ? 'STAFF_REACTIVATED' : 'STAFF_DEACTIVATED',
            entity: 'tenant_users',
            entityId: $membership->id,
            detail: $membership->user->full_name.' was '
                .($membership->is_active ? 'reinstated at' : 'stood down from').' this school',
        );

        session()->flash('status', $membership->is_active
            ? $membership->user->full_name.' can work here again.'
            : $membership->user->full_name.' no longer works here. Their account at any other school is untouched.');
    }

    public function resetPassword(string $membershipId, AuditLogger $audit): void
    {
        $membership = $this->posting($membershipId, ['user']);
        $person = $membership->user;

        $person->forceFill([
            'password' => Hash::make(config('prativa.seed_password')),
            'must_reset_password' => true,
        ])->save();

        $audit->record(
            action: 'STAFF_PASSWORD_RESET',
            entity: 'users',
            entityId: $person->id,
            detail: $person->full_name."'s password was reset by ".auth()->user()->full_name,
        );

        session()->flash('status', $person->full_name.
            ' has been reset to the default password and must change it on next sign-in.'.
            ($person->memberships()->count() > 1
                ? ' This is their login everywhere, so it applies at every school they work at.'
                : ''));
    }

    public function render(): View
    {
        return view('livewire.setup.staff', [
            // Postings at this school. The global scope does not apply to
            // tenant_users — login has to read it before a school is active —
            // so the filter here is explicit and load-bearing.
            'staff' => TenantUser::query()
                ->where('tenant_id', app(TenantContext::class)->idOrFail())
                ->with(['user', 'roleRows', 'auditScopes.location'])
                ->orderBy('staff_code')
                ->get(),
            'allRoles' => Role::cases(),
        ])->title('Staff');
    }
}
