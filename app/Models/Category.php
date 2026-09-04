<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'categories';

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'name', 'code', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Subcategory::class);
    }

    public function itemTypes(): HasMany
    {
        return $this->hasMany(ItemType::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
