<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One role, held by one person, at one school. */
class UserRole extends Model
{
    use HasUuids;

    protected $table = 'user_roles';

    public $timestamps = false;

    protected $fillable = ['tenant_user_id', 'role'];

    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'tenant_user_id');
    }
}
