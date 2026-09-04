<?php

namespace App\Models;

use App\Enums\ReceiptCondition;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The separation-of-duties gate.
 *
 * chk_receipt_two_people refuses any row where received_by_id = ordered_by_id,
 * and trg_receipt_orderer re-reads the purchase order to confirm ordered_by_id
 * genuinely matches it — so the column cannot be faked to slip past the CHECK.
 */
class GoodsReceipt extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'purchase_order_id', 'ordered_by_id', 'received_by_id', 'location_id',
        'condition', 'discrepancy_note', 'challan_no', 'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'condition' => ReceiptCondition::class,
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class, 'receipt_id');
    }
}
