<?php

namespace App\Livewire\Demands;

use App\Models\DemandForm;
use App\Services\DemandService;
use App\Services\SettingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The full trail for one demand form: raised, every signature, the order, who
 * verified the goods, and the bill. All of it timestamped and attributed.
 */
class Show extends Component
{
    public string $demandId;

    public function mount(DemandForm $demand): void
    {
        $this->demandId = $demand->id;
    }

    public function withdraw(DemandService $demands): void
    {
        try {
            $demands->cancel($this->demandId, auth()->user());
        } catch (AuthorizationException $e) {
            $this->addError('withdraw', $e->getMessage());

            return;
        }

        session()->flash('status', 'The demand form has been withdrawn.');
    }

    public function render(DemandService $demands, SettingService $settings): View
    {
        $demand = $demands->find($this->demandId);
        $user = auth()->user();

        abort_unless(
            $user->seesEverything() || $demand->raised_by_id === $user->id || $user->approval_tier > 0,
            403,
            'You can only open demand forms you raised, or ones that come to you to decide.'
        );

        return view('livewire.demands.show', [
            'demand' => $demand,
            'tiers' => $settings->tiers(),
        ])->title($demand->ref);
    }
}
