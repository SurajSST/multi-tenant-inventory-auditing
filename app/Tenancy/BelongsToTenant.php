<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Put this on any model whose table carries tenant_id.
 *
 * It does two things: filters every read down to the active school, and stamps
 * tenant_id on every write so no caller has to remember to. Both halves matter
 * — a model that reads scoped but writes unstamped produces rows that belong
 * to nobody and appear to no one.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $context = app(TenantContext::class);

            // audit_log is the one table that may legitimately hold a row
            // belonging to no school — a platform owner acts above all of them.
            // Everywhere else, a missing tenant is a bug worth stopping for.
            $model->setAttribute(
                'tenant_id',
                $model->tenantIsOptional() ? $context->id() : $context->idOrFail(),
            );
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Overridden by the few models that may hold a school-less row. */
    public function tenantIsOptional(): bool
    {
        return false;
    }

    /** Escape hatch for the platform console. Never for ordinary screens. */
    public function scopeAcrossTenants($query)
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
