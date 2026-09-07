<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'purchase_order_id', 'demand_line_id', 'item_type_id',
        'description', 'quantity_ordered', 'unit', 'unit_price', 'discount',
        'tax', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'line_total' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function demandLine(): BelongsTo
    {
        return $this->belongsTo(DemandLine::class, 'demand_line_id');
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    public function receiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class, 'purchase_order_line_id');
    }

    public function billLines(): HasMany
    {
        return $this->hasMany(BillLine::class, 'purchase_order_line_id');
    }

    public function totalReceivedQty(): int
    {
        if ($this->relationLoaded('receiptLines')) {
            return (int) $this->receiptLines->sum('qty_received');
        }

        return (int) $this->receiptLines()->sum('qty_received');
    }

    public function remainingQty(): int
    {
        return max(0, $this->quantity_ordered - $this->totalReceivedQty());
    }
}
