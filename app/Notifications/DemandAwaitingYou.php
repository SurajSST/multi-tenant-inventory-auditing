<?php

namespace App\Notifications;

use App\Models\DemandForm;
use App\Models\Tenant;
use App\Support\Money;

/**
 * A form has reached your band and is waiting for your decision.
 *
 * This is the notification the system most needed. The approval ladder only
 * moves when somebody looks at it, and nothing used to say there was anything
 * to look at.
 */
class DemandAwaitingYou extends SchoolNotification
{
    public function __construct(
        Tenant $tenant,
        public string $ref,
        public string $department,
        public string $raisedBy,
        public string $amount,
        public int $tierNo,
    ) {
        parent::__construct($tenant);
    }

    public static function for(Tenant $tenant, DemandForm $demand): self
    {
        return new self(
            $tenant,
            $demand->ref,
            $demand->department,
            $demand->raisedBy->full_name,
            Money::npr($demand->total_amount),
            (int) $demand->current_tier,
        );
    }

    public function headline(): string
    {
        return "{$this->ref} is waiting for your decision";
    }

    public function details(): array
    {
        return [
            "Raised by {$this->raisedBy} for {$this->department}.",
            "Value: {$this->amount}. It sits at tier {$this->tierNo} — your band.",
        ];
    }

    public function actionLabel(): string
    {
        return 'Open the approvals queue';
    }

    public function actionUrl(): string
    {
        return '/demands/queue';
    }
}
