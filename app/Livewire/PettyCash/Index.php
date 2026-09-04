<?php

namespace App\Livewire\PettyCash;

use App\Enums\TokenStatus;
use App\Models\PettyCashToken;
use App\Services\PettyCashService;
use App\Support\Money;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    public ?string $voidingId = null;

    public string $voidReason = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function service(): PettyCashService
    {
        return app(PettyCashService::class);
    }

    public function pay(string $tokenId): void
    {
        $token = $this->service->markPaid($tokenId, auth()->user());

        session()->flash('status', "{$token->serial} settled — ".
            Money::npr($token->amount)." paid to {$token->claimant_name}.");
    }

    public function sendToAccounts(string $tokenId): void
    {
        $token = $this->service->sendToAccounts($tokenId, auth()->user());

        session()->flash('status', "{$token->serial} is now with Accounts for review.");
    }

    public function openVoid(string $tokenId): void
    {
        $this->voidingId = $tokenId;
        $this->voidReason = '';
        $this->resetErrorBag();
    }

    public function closeVoid(): void
    {
        $this->reset(['voidingId', 'voidReason']);
    }

    public function void(): void
    {
        $token = $this->service->void($this->voidingId, $this->voidReason, auth()->user());

        $this->closeVoid();

        session()->flash('status', "{$token->serial} voided. It stays on record with your reason attached.");
    }

    public function render(): View
    {
        return view('livewire.petty-cash.index', [
            'tokens' => $this->service->list($this->status ? TokenStatus::from($this->status) : null),
            'summary' => $this->service->summary(),
            'monthlySpend' => $this->service->monthlySpend(),
            'voiding' => $this->voidingId ? PettyCashToken::find($this->voidingId) : null,
        ])->title('Petty Cash');
    }
}
