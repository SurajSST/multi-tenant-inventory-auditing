<?php

namespace App\Models;

use App\Enums\UnitStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-unit register for durables that need tracking one by one — laptops,
 * projectors, smart boards. unit_no is the running number after the code
 * prefix, so LAPTOP.7 is unit_no 7 of item type LAPTOP.
 */
class AssetUnit extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'item_type_id', 'unit_no', 'unit_code', 'location_id', 'serial_no',
        'status', 'acquired_on', 'purchase_cost', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
            'unit_no' => 'integer',
            'acquired_on' => 'date',
            'purchase_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
