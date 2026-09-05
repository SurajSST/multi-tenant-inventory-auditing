<?php

namespace App\Models;

use App\Enums\Role;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * WHO SOMEBODY IS. Nothing on this model is specific to a school.
 *
 * What they ARE at a school — staff code, designation, approval tier, roles,
 * auditor block scope — lives on their posting (TenantUser). The methods below
 * keep the names the rest of the application already calls, and answer them
 * from the posting at the school currently active. That is what lets the gates,
 * the middleware, the services and every Blade @can carry on unchanged while
 * one person holds different jobs at different schools.
 */
class User extends Authenticatable
{
    use HasUuids, Notifiable;

    protected $fillable = [
        'full_name', 'email', 'phone', 'password',
        'is_active', 'must_reset_password',
    ];

    /**
     * is_platform_owner is deliberately NOT fillable. It is the one flag that
     * crosses every school boundary in the system, and it is set explicitly by
     * the seeder or by another platform owner — never by a mass assignment.
     */
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform_owner' => 'boolean',
            'must_reset_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // ── postings ─────────────────────────────────────────────

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    /** Every school this person can currently work at. */
    public function activeMemberships(): Collection
    {
        return $this->memberships()
            ->where('is_active', true)
            ->with('tenant')
            ->get()
            ->filter(fn (TenantUser $m) => $m->tenant?->is_active)
            ->values();
    }

    /**
     * Resolved postings, kept for the life of the request.
     *
     * Every hasRole(), every @can, every ->designation and ->approval_tier goes
     * through here. Without the cache the signed-in user's own posting — and
     * its roles — were re-read from the database dozens of times on a single
     * page render. Keyed by school, so switching does not read a stale answer.
     *
     * @var array<string, TenantUser|null>
     */
    private array $resolvedMemberships = [];

    public function membershipFor(Tenant|string|null $tenant): ?TenantUser
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        if (! $tenantId) {
            return null;
        }

        if (array_key_exists($tenantId, $this->resolvedMemberships)) {
            return $this->resolvedMemberships[$tenantId];
        }

        return $this->resolvedMemberships[$tenantId] = $this->memberships()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            // Roles are asked for immediately after the posting, every time.
            ->with('roleRows')
            ->first();
    }

    /**
     * Drop the cache. Called after roles or a posting change within the same
     * request, so the screen that made the change re-reads what it just wrote.
     */
    public function forgetMemberships(): static
    {
        $this->resolvedMemberships = [];
        $this->unsetRelation('currentMembership');

        return $this;
    }

    /**
     * The posting at the school active right now, as a relation, so that lists
     * showing other people's designations can eager-load it.
     *
     * Screens print $demand->raisedBy->designation once per row. Resolved by a
     * query each time that is one round trip per row; resolved through a loaded
     * relation it is none.
     */
    public function currentMembership(): HasOne
    {
        return $this->hasOne(TenantUser::class)
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('is_active', true);
    }

    /**
     * The posting at the school active right now. Null for a platform owner who
     * holds none, and null before a school has been chosen — in both cases every
     * role question below answers "no", which is the safe direction.
     */
    public function activeMembership(): ?TenantUser
    {
        // Prefer the eager-loaded relation; fall back to a lookup for the
        // signed-in user, who is resolved once per request either way.
        if ($this->relationLoaded('currentMembership')) {
            return $this->getRelation('currentMembership');
        }

        return $this->membershipFor(app(TenantContext::class)->id());
    }

    public function isPlatformOwner(): bool
    {
        return (bool) $this->is_platform_owner;
    }

    // ── what they are, here ──────────────────────────────────
    //
    // Accessors so that $user->approval_tier, ->staff_code and ->designation
    // read the same way they did when they were columns on this table.

    protected function approvalTier(): Attribute
    {
        return Attribute::get(fn () => $this->activeMembership()?->approval_tier ?? 0);
    }

    protected function staffCode(): Attribute
    {
        return Attribute::get(fn () => $this->activeMembership()?->staff_code);
    }

    protected function designation(): Attribute
    {
        return Attribute::get(fn () => $this->activeMembership()?->designation ?? '');
    }

    // ── roles, at the school currently active ────────────────

    /** @return Collection<int, Role> */
    public function roles(): Collection
    {
        return $this->activeMembership()?->roles() ?? collect();
    }

    public function hasRole(Role $role): bool
    {
        return $this->roles()->contains($role);
    }

    /** @param  array<int, Role>  $roles */
    public function hasAnyRole(array $roles): bool
    {
        $mine = $this->roles();

        // Not Collection::intersect — that goes through array_intersect, which
        // string-casts and blows up on enum instances.
        foreach ($roles as $role) {
            if ($mine->contains($role)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * Super Admin, Accounts, Chairman and the Purchase Officer see every demand
     * form at their school. Everybody else sees only the ones they raised. The
     * platform owner sees everything everywhere, but cannot act on any of it.
     */
    public function seesEverything(): bool
    {
        return $this->isPlatformOwner() || $this->hasAnyRole(Role::seesEverything());
    }

    /**
     * Governance leadership who may inspect the entire school's audit trail.
     * Normal staff (accounts, teachers, storekeepers, etc.) may only inspect their own actions.
     */
    public function canViewAllAuditTrail(): bool
    {
        return $this->isPlatformOwner()
            || $this->hasAnyRole([Role::SUPER_ADMIN, Role::CHAIRMAN])
            || $this->approval_tier >= 3;
    }

    /** Blocks this auditor may count in at the active school. Empty means all. */
    public function scopedLocationIds(): array
    {
        return $this->activeMembership()?->scopedLocationIds() ?? [];
    }

    // ── what they have done ──────────────────────────────────

    public function demandsRaised(): HasMany
    {
        return $this->hasMany(DemandForm::class, 'raised_by_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DemandApproval::class, 'actor_id');
    }

    public function ordersPlaced(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'ordered_by_id');
    }

    public function receiptsMade(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'received_by_id');
    }

    public function countsEntered(): HasMany
    {
        return $this->hasMany(StockCountEntry::class, 'counted_by_id');
    }

    /**
     * Notifications belonging to the school currently active.
     *
     * Somebody who works at two schools should not see Everest approvals
     * waiting for them while they are working in Prativa — the alert would be
     * true but unactionable, since their roles there are different and the link
     * goes somewhere they cannot currently reach.
     */
    public function notificationsHere(): MorphMany
    {
        return $this->notifications()
            ->where('tenant_id', app(TenantContext::class)->id());
    }

    /** Short form for tables and timelines: "S. Sharma — Managing Director". */
    public function nameWithRole(): string
    {
        $designation = $this->designation;

        return $designation ? "{$this->full_name} — {$designation}" : $this->full_name;
    }
}
