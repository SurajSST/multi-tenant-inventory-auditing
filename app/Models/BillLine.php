<?php

namespace App\Models;

use App\Support\Money;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillLine extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'bill_id', 'purchase_order_line_id', 'item_type_id',
        'description', 'quantity', 'unit_price', 'discount', 'tax', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'line_total' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function poLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    public function isPriceMatched(): bool
    {
        if (! $this->poLine) {
            return true;
        }

        return Money::eq($this->unit_price, $this->poLine->unit_price);
    }

    public function isQuantityMatched(): bool
    {
        if (! $this->poLine) {
            return true;
        }

        return $this->quantity <= $this->poLine->totalReceivedQty();
    }
}
