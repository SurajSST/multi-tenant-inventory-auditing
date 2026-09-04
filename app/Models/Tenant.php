<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One school. Everything else in the system hangs off one of these. */
class Tenant extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'short_name', 'address', 'logo_url', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function logoUrl(?string $fallback = '/img/logo/prativaLogoWhite.png'): string
    {
        return $this->logo_url ?: $fallback;
    }
}
