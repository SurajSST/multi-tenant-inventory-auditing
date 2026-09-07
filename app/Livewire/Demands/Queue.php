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
    public string $search = '';

    public ?string $decidingId = null;

    public string $action = '';

    public string $reason = '';

    public string $minuteRef = '';

    /** @var array<string> */
    public array $selected = [];

    public bool $selectAll = false;

    public bool $bulkModal = false;

    public string $bulkMinuteRef = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->approval_tier > 0 || auth()->user()->can('approve-demands'), 403, 'You do not have pending approvals.');
    }

    public function updatedSelectAll(bool $value, DemandService $demands): void
    {
        if ($value) {
            $queue = $demands->myQueue(auth()->user());
            if ($this->search) {
                $needle = strtolower(trim($this->search));
                $queue = $queue->filter(function ($item) use ($needle) {
                    return str_contains(strtolower($item->ref ?? ''), $needle)
                        || str_contains(strtolower($item->department ?? ''), $needle)
                        || str_contains(strtolower($item->justification ?? ''), $needle)
                        || str_contains(strtolower($item->raisedBy?->full_name ?? ''), $needle)
                        || $item->lines->contains(fn ($l) => str_contains(strtolower($l->item_name ?? ''), $needle));
                });
            }

            $this->selected = $queue
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();
        } else {
            $this->selected = [];
        }
    }

    public function openBulkModal(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $this->bulkModal = true;
        $this->bulkMinuteRef = '';
        $this->resetErrorBag();
    }

    public function closeBulkModal(): void
    {
        $this->bulkModal = false;
        $this->bulkMinuteRef = '';
    }

    public function approveSelected(DemandService $demands): void
    {
        if (empty($this->selected)) {
            return;
        }

        $approvedCount = 0;
        $errors = [];

        foreach ($this->selected as $demandId) {
            try {
                $demands->decide(
                    demandId: $demandId,
                    action: ApprovalAction::APPROVE,
                    user: auth()->user(),
                    reason: null,
                    minuteRef: $this->bulkMinuteRef ?: null,
                );
                $approvedCount++;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $this->selected = [];
        $this->selectAll = false;
        $this->closeBulkModal();

        if ($errors) {
            $msg = "Approved {$approvedCount} demand(s). Some could not be approved: ".implode('; ', array_unique($errors));
            session()->flash('status', $msg);
            $this->dispatch('toast', message: $msg, tone: 'warn', title: 'Bulk Approval Warning');
        } else {
            $msg = "Successfully approved {$approvedCount} demand form(s).";
            session()->flash('status', $msg);
            $this->dispatch('toast', message: $msg, tone: 'ok', title: 'Bulk Approved');
        }
    }

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
            $this->dispatch('toast', message: $e->getMessage(), tone: 'danger', title: 'Approval Refused');

            return;
        }

        $this->close();

        $msg = $action === ApprovalAction::APPROVE
            ? "{$demand->ref} approved. ".($demand->current_tier
                ? "It now sits with tier {$demand->current_tier}."
                : 'It is fully approved and ready for an order.')
            : "{$demand->ref} rejected. The person who raised it can see your reason.";

        session()->flash('status', $msg);
        $this->dispatch('toast', message: $msg, tone: $action === ApprovalAction::APPROVE ? 'ok' : 'warn', title: $action === ApprovalAction::APPROVE ? 'Approved' : 'Rejected');
    }

    public function render(DemandService $demands, SettingService $settings): View
    {
        $queue = $demands->myQueue(auth()->user());

        if ($this->search) {
            $needle = strtolower(trim($this->search));
            $queue = $queue->filter(function ($item) use ($needle) {
                return str_contains(strtolower($item->ref ?? ''), $needle)
                    || str_contains(strtolower($item->department ?? ''), $needle)
                    || str_contains(strtolower($item->justification ?? ''), $needle)
                    || str_contains(strtolower($item->raisedBy?->full_name ?? ''), $needle)
                    || $item->lines->contains(fn ($l) => str_contains(strtolower($l->item_name ?? ''), $needle));
            })->values();
        }

        return view('livewire.demands.queue', [
            'queue' => $queue,
            'tiers' => $settings->tiers(),
            'deciding' => $this->decidingId ? $queue->firstWhere('id', $this->decidingId) : null,
        ])->title('My Approvals');
    }
}
