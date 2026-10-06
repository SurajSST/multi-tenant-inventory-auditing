<?php

namespace App\Notifications;

use App\Models\Tenant;

/** A purchase order has been placed and is waiting for independent receipt verification. */
class OrderAwaitingReceipt extends SchoolNotification
{
    public function __construct(
        Tenant $tenant,
        public string $ref,
        public string $vendor,
        public string $amount,
    ) {
        parent::__construct($tenant);
    }

    public function headline(): string
    {
        return "{$this->ref} is waiting for goods verification";
    }

    public function details(): array
    {
        return ["Order placed with {$this->vendor} for {$this->amount}."];
    }

    public function actionLabel(): string
    {
        return 'Review orders awaiting receipt';
    }

    public function actionUrl(): string
    {
        return '/orders?pendingReceipt=1';
    }
}
