<?php

namespace App\Livewire\Demands;

use App\Enums\ApprovalAction;
use App\Services\DemandService;
use App\Services\SettingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * What genuinely sits with the signed-in approver — their tier, and never a
 * form they raised themselves.
 */
class Queue extends Component
{
    public ?string $decidingId = null;

    public string $action = '';

    public string $reason = '';

    public string $minuteRef = '';

    public function open(string $demandId, string $action): void
    {
        $this->decidingId = $demandId;
        $this->action = $action;
        $this->reason = '';
        $this->minuteRef = '';
        $this->resetErrorBag();
    }

    public function close(): void
    {
        $this->reset(['decidingId', 'action', 'reason', 'minuteRef']);
    }

    public function confirm(DemandService $demands): void
    {
        $action = ApprovalAction::from($this->action);

        try {
            $demand = $demands->decide(
                demandId: $this->decidingId,
                action: $action,
                user: auth()->user(),
                reason: $this->reason ?: null,
                minuteRef: $this->minuteRef ?: null,
            );
        } catch (AuthorizationException $e) {
            $this->addError('decision', $e->getMessage());

            return;
        }

        $this->close();

        session()->flash('status', $action === ApprovalAction::APPROVE
            ? "{$demand->ref} approved. ".($demand->current_tier
                ? "It now sits with tier {$demand->current_tier}."
                : 'It is fully approved and ready for an order.')
            : "{$demand->ref} rejected. The person who raised it can see your reason.");
    }

    public function render(DemandService $demands, SettingService $settings): View
    {
        $queue = $demands->myQueue(auth()->user());

        return view('livewire.demands.queue', [
            'queue' => $queue,
            'tiers' => $settings->tiers(),
            'deciding' => $this->decidingId ? $queue->firstWhere('id', $this->decidingId) : null,
        ])->title('My Approvals');
    }
}
