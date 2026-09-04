<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptLine extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'receipt_id', 'demand_line_id', 'qty_ordered', 'qty_received', 'remark',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'integer',
            'qty_received' => 'integer',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'receipt_id');
    }

    public function demandLine(): BelongsTo
    {
        return $this->belongsTo(DemandLine::class, 'demand_line_id');
    }

    public function isShort(): bool
    {
        return $this->qty_received < $this->qty_ordered;
    }
}
