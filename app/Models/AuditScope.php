<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which blocks an auditor is assigned to count in. No rows = every block at
 * that school. Keyed on the posting rather than the person, because somebody
 * auditing two schools has a separate scope at each.
 */
class AuditScope extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'tenant_user_id', 'location_id'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'tenant_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
