<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandLine extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'demand_id', 'item_type_id', 'item_name', 'quantity',
        'unit_rate', 'line_total', 'specification',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(DemandForm::class, 'demand_id');
    }

    /** Null when the requester asked for something not yet on the register. */
    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class);
    }

    public function receiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class, 'demand_line_id');
    }

    public function isNewItem(): bool
    {
        return $this->item_type_id === null;
    }
}
