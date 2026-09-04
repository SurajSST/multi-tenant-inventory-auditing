<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A posting: what one person is at one school.
 *
 * Staff code, designation, approval tier and roles all live here rather than on
 * the user, because they are facts about the job, not the person. Somebody can
 * be the Accounts Officer at one school and a Stock Auditor at another, and be
 * stood down from one without losing the other.
 *
 * Deliberately NOT scoped by BelongsToTenant: the login has to find a person's
 * postings before any school is active.
 */
class TenantUser extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'staff_code', 'designation',
        'approval_tier', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'approval_tier' => 'integer',
            'is_active' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleRows(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function auditScopes(): HasMany
    {
        return $this->hasMany(AuditScope::class);
    }

    /** @return Collection<int, Role> */
    public function roles(): Collection
    {
        return $this->roleRows->pluck('role')->filter()->values();
    }

    public function hasRole(Role $role): bool
    {
        return $this->roles()->contains($role);
    }

    /** Replaces the whole role set for this posting in one go. */
    public function syncRoles(array $roles): void
    {
        $wanted = collect($roles)
            ->map(fn ($r) => $r instanceof Role ? $r->value : $r)
            ->unique();

        $this->roleRows()->whereNotIn('role', $wanted->all() ?: ['-'])->delete();

        foreach ($wanted as $role) {
            $this->roleRows()->firstOrCreate(['role' => $role]);
        }

        $this->unsetRelation('roleRows');
    }

    /** Blocks this auditor may count in at this school. Empty means all of them. */
    public function scopedLocationIds(): array
    {
        return $this->auditScopes()->pluck('location_id')->all();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
