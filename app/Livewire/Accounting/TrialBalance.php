<?php

namespace App\Livewire\Accounting;

use App\Services\AccountingService;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TrialBalance extends Component
{
    public ?string $asOfDate = null;

    public function mount(): void
    {
        $this->asOfDate = now()->toDateString();
    }

    #[Computed]
    public function report(): array
    {
        return app(AccountingService::class)->trialBalance($this->asOfDate);
    }

    public function render(): View
    {
        return view('livewire.accounting.trial-balance');
    }
}
