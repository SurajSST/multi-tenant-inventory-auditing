<?php

namespace App\Livewire\Setup;

use App\Services\SettingService;
use App\Support\Money;
use Illuminate\View\View;
use Livewire\Component;

class Settings extends Component
{
    public string $schoolName = '';

    public string $pettyCashCeiling = '';

    public bool $allowOrderAboveApproval = true;

    public function mount(SettingService $settings): void
    {
        $this->schoolName = $settings->schoolName();
        $this->pettyCashCeiling = $settings->pettyCashCeiling();
        $this->allowOrderAboveApproval = $settings->allowOrderAboveApproval();
    }

    public function save(SettingService $settings): void
    {
        $this->validate([
            'schoolName' => ['required', 'string', 'max:180'],
            'pettyCashCeiling' => ['required', 'numeric', 'gt:0'],
        ]);

        $user = auth()->user();

        $settings->set(SettingService::SCHOOL_NAME, $this->schoolName, $user);
        $settings->set(SettingService::PETTY_CASH_CEILING, Money::of($this->pettyCashCeiling), $user);
        $settings->set(SettingService::ALLOW_ORDER_ABOVE_APPROVAL, $this->allowOrderAboveApproval, $user);

        session()->flash('status', 'Settings saved. Tokens already issued keep the ceiling that was in force when they were created.');
    }

    public function render(): View
    {
        return view('livewire.setup.settings')->title('Settings');
    }
}
