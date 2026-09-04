<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use RuntimeException;

/**
 * Filters every query on a tenant-owned model down to the active school.
 *
 * The important part is what happens when NO school is active: this throws
 * rather than quietly returning every school's rows. A forgotten scope that
 * silently widens a query is exactly the failure this system cannot afford —
 * it would not look like a bug, it would look like data.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        // The platform console reading across schools, deliberately.
        if ($context->isUnscoped()) {
            return;
        }

        if (! $context->has()) {
            throw new RuntimeException(sprintf(
                'Tried to query %s with no school active. Wrap the work in '.
                'TenantContext::runFor($tenant, …), or runUnscoped() if reading '.
                'across schools is genuinely what was meant.',
                class_basename($model),
            ));
        }

        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $context->id(),
        );
    }
}
