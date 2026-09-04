<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcategory extends Model
{
    use BelongsToTenant, HasUuids;

    protected $table = 'subcategories';

    public $timestamps = false;

    protected $fillable = ['tenant_id', 'category_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
