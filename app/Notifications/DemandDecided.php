<?php

namespace App\Notifications;

use App\Models\Tenant;

/** Your form was approved, rejected, or sent back. */
class DemandDecided extends SchoolNotification
{
    public function __construct(
        Tenant $tenant,
        public string $demandId,
        public string $ref,
        public string $outcome,      // APPROVED · REJECTED · RETURNED
        public string $decidedBy,
        public ?string $reason = null,
    ) {
        parent::__construct($tenant);
    }

    public function headline(): string
    {
        return match ($this->outcome) {
            'APPROVED' => "{$this->ref} has been fully approved",
            'REJECTED' => "{$this->ref} was rejected",
            default => "{$this->ref} was sent back to you",
        };
    }

    public function details(): array
    {
        $lines = ['Decided by '.$this->decidedBy.'.'];

        if ($this->reason) {
            $lines[] = 'Reason given: '.$this->reason;
        }

        if ($this->outcome === 'APPROVED') {
            $lines[] = 'It now goes to the Purchase Officer to be ordered.';
        }

        return $lines;
    }

    public function actionLabel(): string
    {
        return 'View the form';
    }

    public function actionUrl(): string
    {
        return '/demands/'.$this->demandId;
    }
}
