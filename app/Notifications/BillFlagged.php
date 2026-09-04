<?php

namespace App\Notifications;

use App\Models\Tenant;

/**
 * A bill did not match what was approved and what was ordered.
 *
 * This is the one that matters financially: an unnoticed mismatch is money
 * leaving on a figure nobody agreed to.
 */
class BillFlagged extends SchoolNotification
{
    public function __construct(
        Tenant $tenant,
        public string $billNo,
        public string $vendor,
        public string $billed,
        public string $ordered,
        public string $variance,
        public string $enteredBy,
    ) {
        parent::__construct($tenant);
    }

    public function headline(): string
    {
        return "Bill {$this->billNo} does not match the order";
    }

    public function details(): array
    {
        return [
            "{$this->vendor} billed {$this->billed} against an order of {$this->ordered}.",
            "That is a variance of {$this->variance}.",
            "Entered by {$this->enteredBy}. It needs a written reason before it can be cleared.",
        ];
    }

    public function actionLabel(): string
    {
        return 'Review the bill register';
    }

    public function actionUrl(): string
    {
        return '/bills';
    }
}
