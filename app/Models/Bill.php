<?php

namespace App\Models;

use App\Enums\MatchStatus;
use App\Support\Money;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bill extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'bill_no', 'fiscal_year', 'purchase_order_id', 'vendor_id', 'bill_date',
        'bill_amount', 'vat_amount', 'approved_amount', 'ordered_amount',
        'variance_amount', 'match_status', 'payment_status', 'paid_amount', 'attachment_path', 'entered_by_id',
        'cleared_by_id', 'cleared_at', 'variance_note',
    ];

    protected function casts(): array
    {
        return [
            'match_status' => MatchStatus::class,
            'bill_date' => 'date',
            'bill_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'approved_amount' => 'decimal:2',
            'ordered_amount' => 'decimal:2',
            'variance_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'entered_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_id');
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isFlagged(): bool
    {
        return $this->match_status === MatchStatus::MISMATCH;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'PAID';
    }

    public function remainingBalance(): string
    {
        return Money::sub($this->bill_amount, $this->paid_amount ?: '0.00');
    }
}
