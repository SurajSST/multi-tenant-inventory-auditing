<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseOrder extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'ref', 'fiscal_year', 'demand_id', 'vendor_id', 'order_amount',
        'expected_date', 'note', 'status', 'ordered_by_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'order_amount' => 'decimal:2',
            'expected_date' => 'date',
            'ordered_at' => 'datetime',
        ];
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(DemandForm::class, 'demand_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(GoodsReceipt::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function isReceived(): bool
    {
        return $this->receipt()->exists();
    }
}
