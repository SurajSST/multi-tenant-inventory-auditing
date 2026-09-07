<?php

namespace App\Models;

use App\Enums\DemandStatus;
use App\Support\Money;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemandForm extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'ref', 'fiscal_year', 'raised_by_id', 'department', 'justification',
        'need_by_date', 'total_amount', 'status', 'current_tier', 'final_tier',
    ];

    protected function casts(): array
    {
        return [
            'status' => DemandStatus::class,
            'total_amount' => 'decimal:2',
            'need_by_date' => 'date',
            'current_tier' => 'integer',
            'final_tier' => 'integer',
            'created_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DemandLine::class, 'demand_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DemandApproval::class, 'demand_id')->orderBy('acted_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'demand_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->orders();
    }

    public function isPending(): bool
    {
        return $this->status === DemandStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === DemandStatus::APPROVED;
    }

    /** How far up the ladder it still has to climb. */
    public function tiersRemaining(): int
    {
        return $this->current_tier === null
            ? 0
            : max(0, $this->final_tier - $this->current_tier + 1);
    }

    public function isFullyOrdered(): bool
    {
        if ($this->lines->isEmpty()) {
            return false;
        }

        foreach ($this->lines as $line) {
            if ($line->remainingToOrderQty() > 0) {
                return false;
            }
        }

        return true;
    }

    public function remainingAmountToOrder(): string
    {
        $total = '0.00';
        foreach ($this->lines as $line) {
            $rem = $line->remainingToOrderQty();
            if ($rem > 0) {
                $total = Money::add($total, Money::mul($line->unit_rate, $rem));
            }
        }

        return $total;
    }
}
