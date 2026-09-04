<?php

namespace App\Models;

use App\Enums\CountSource;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * APPEND ONLY. The database refuses UPDATE and DELETE on this table.
 *
 * Stock is a ledger, not a number. The current quantity for an
 * (item type, block) pair is the newest row — read through v_current_stock —
 * so the displayed figure and the history can never disagree. Correcting a
 * miscount adds a row carrying the previous figure; it never erases one.
 */
class StockCountEntry extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'item_type_id', 'location_id', 'quantity', 'previous_qty',
        'source', 'reference_id', 'note', 'counted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => CountSource::class,
            'quantity' => 'integer',
            'previous_qty' => 'integer',
            'counted_at' => 'datetime',
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

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by_id');
    }

    /** How the figure moved, for the history table: "12 → 15". */
    public function movement(): string
    {
        return $this->previous_qty.' → '.$this->quantity;
    }

    public function delta(): int
    {
        return $this->quantity - $this->previous_qty;
    }
}
