<?php

namespace App\Models;

use App\Enums\TokenStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashToken extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'serial', 'fiscal_year', 'bill_no', 'bill_date', 'vendor_name', 'amount',
        'ceiling_at_issue', 'claimant_name', 'purpose', 'bill_sighted', 'status',
        'issued_by_id', 'paid_by_id', 'paid_at', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => TokenStatus::class,
            'bill_date' => 'date',
            'amount' => 'decimal:2',
            'ceiling_at_issue' => 'decimal:2',
            'bill_sighted' => 'boolean',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, TokenStatus::open(), true);
    }
}
