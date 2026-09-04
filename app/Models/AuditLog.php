<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * APPEND ONLY. The database refuses UPDATE and DELETE on this table.
 *
 * Every action in the system lands here, attributed to a named signed-in user.
 * This is what Admin and the MD read when they want to know who did what.
 */
class AuditLog extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'audit_log';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'actor_id', 'action', 'entity', 'entity_id', 'detail',
        'before', 'after', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'at' => 'datetime',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    /**
     * A platform owner's actions sit above any one school, so their trail rows
     * carry no tenant. Everything a school does carries theirs.
     */
    public function tenantIsOptional(): bool
    {
        return true;
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
