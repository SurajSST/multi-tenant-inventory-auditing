<?php

namespace App\Models;

use App\Support\Money;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A configurable value band in the approval ladder. Editable in Setup. */
class ApprovalTier extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'tier_no', 'min_amount', 'max_amount', 'decider_label',
        'requires_minute', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tier_no' => 'integer',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'requires_minute' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DemandApproval::class, 'tier_no', 'tier_no');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Does a value of this size land in this band? */
    public function covers(int|float|string $amount): bool
    {
        if (Money::lt($amount, $this->min_amount)) {
            return false;
        }

        return $this->max_amount === null || Money::lte($amount, $this->max_amount);
    }

    /** "Rs. 15,001.00 – Rs. 50,000.00" or "Above Rs. 200,000.00". */
    public function range(): string
    {
        return $this->max_amount === null
            ? 'Above '.Money::npr(Money::sub($this->min_amount, 1))
            : Money::npr($this->min_amount).' – '.Money::npr($this->max_amount);
    }
}
