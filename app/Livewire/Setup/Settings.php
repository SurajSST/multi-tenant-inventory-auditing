<?php

namespace App\Livewire\Setup;

use App\Enums\DemandApprovalMode;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class Settings extends Component
{
    public string $schoolName = '';

    public string $pettyCashCeiling = '';

    public bool $allowOrderAboveApproval = true;

    public string $demandApprovalMode = 'sequential';

    public function mount(SettingService $settings): void
    {
        $this->schoolName = $settings->schoolName();
        $this->pettyCashCeiling = $settings->pettyCashCeiling();
        $this->allowOrderAboveApproval = $settings->allowOrderAboveApproval();
        $this->demandApprovalMode = $settings->demandApprovalMode()->value;
    }

    public function save(SettingService $settings): void
    {
        $this->validate([
            'schoolName' => ['required', 'string', 'max:180'],
            'pettyCashCeiling' => ['required', 'numeric', 'gt:0'],
            'demandApprovalMode' => ['required', Rule::enum(DemandApprovalMode::class)],
        ]);

        $user = auth()->user();

        $settings->set(SettingService::SCHOOL_NAME, $this->schoolName, $user);
        $settings->set(SettingService::PETTY_CASH_CEILING, Money::of($this->pettyCashCeiling), $user);
        $settings->set(SettingService::ALLOW_ORDER_ABOVE_APPROVAL, $this->allowOrderAboveApproval, $user);
        $settings->set(SettingService::DEMAND_APPROVAL_MODE, $this->demandApprovalMode, $user);

        $msg = 'Settings saved. Changes are effective immediately.';
        $this->dispatch('toast', message: $msg, tone: 'success', title: 'Settings saved');
    }

    public function render(): View
    {
        return view('livewire.setup.settings', [
            'approvalModes' => DemandApprovalMode::cases(),
        ])->title('Settings');
    }
}
