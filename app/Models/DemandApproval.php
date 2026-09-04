<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * APPEND ONLY. The database refuses UPDATE and DELETE, and a trigger refuses
 * any row where the actor is the person who raised the form.
 *
 * One decision per tier per form, timestamped and tied to a named approver.
 */
class DemandApproval extends Model
{
    use BelongsToTenant, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'demand_id', 'tier_no', 'actor_id', 'action', 'reason', 'minute_ref',
    ];

    protected function casts(): array
    {
        return [
            'action' => ApprovalAction::class,
            'tier_no' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(DemandForm::class, 'demand_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(ApprovalTier::class, 'tier_no', 'tier_no');
    }
}
