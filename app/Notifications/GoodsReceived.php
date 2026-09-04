<?php

namespace App\Notifications;

use App\Models\Tenant;

/**
 * Somebody other than you has verified the delivery for your order — which is
 * the point: the person who orders never confirms their own goods.
 */
class GoodsReceived extends SchoolNotification
{
    public function __construct(
        Tenant $tenant,
        public string $orderId,
        public string $ref,
        public string $receivedBy,
        public string $condition,
        public ?string $discrepancy = null,
    ) {
        parent::__construct($tenant);
    }

    public function headline(): string
    {
        return $this->discrepancy
            ? "{$this->ref} was delivered with a discrepancy"
            : "{$this->ref} has been verified as received";
    }

    public function details(): array
    {
        $lines = ["Checked in by {$this->receivedBy}. Condition recorded as {$this->condition}."];

        if ($this->discrepancy) {
            $lines[] = 'Noted: '.$this->discrepancy;
        }

        return $lines;
    }

    public function actionLabel(): string
    {
        return 'View the order';
    }

    public function actionUrl(): string
    {
        return '/orders/'.$this->orderId;
    }
}
