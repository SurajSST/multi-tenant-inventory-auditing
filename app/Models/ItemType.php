<?php

namespace App\Models;

use App\Enums\Lifespan;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per KIND of thing — "Chair — Senior" with prefix CHAIR.S.
 * The individual physical units are CHAIR.S.1, CHAIR.S.2 … see AssetUnit and
 * InventoryService::unitCodes().
 */
class ItemType extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'name', 'code_prefix', 'category_id', 'subcategory_id', 'lifespan',
        'unit_of_measure', 'indicative_rate', 'reorder_level', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'lifespan' => Lifespan::class,
            'indicative_rate' => 'decimal:2',
            'reorder_level' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function counts(): HasMany
    {
        return $this->hasMany(StockCountEntry::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(AssetUnit::class);
    }

    public function demandLines(): HasMany
    {
        return $this->hasMany(DemandLine::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
