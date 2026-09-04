<?php

namespace App\Livewire\PettyCash;

use App\Services\PettyCashService;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Issuing a petty cash token.
 *
 * The ceiling is checked live as the amount is typed, so a claimant is told
 * straight away that their bill has to go through the demand form route
 * instead. The claimant's name is recorded because the issuer is meant to be
 * looking at them, and the original bill, when the token is created.
 */
class Issue extends Component
{
    public string $billNo = '';

    public string $billDate = '';

    public string $vendorName = '';

    public string $amount = '';

    public string $claimantName = '';

    public string $purpose = '';

    public bool $billSighted = false;

    public function mount(): void
    {
        $this->billDate = now()->toDateString();
    }

    #[Computed]
    public function ceiling(): string
    {
        return app(SettingService::class)->pettyCashCeiling();
    }

    #[Computed]
    public function overCeiling(): bool
    {
        return $this->amount !== '' && Money::gt($this->amount, $this->ceiling);
    }

    public function save(PettyCashService $petty): void
    {
        $this->validate([
            'billNo' => ['required', 'string', 'max:80'],
            'billDate' => ['nullable', 'date'],
            'vendorName' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'claimantName' => ['required', 'string', 'max:120'],
            'purpose' => ['required', 'string', 'max:255'],
            'billSighted' => ['accepted'],
        ], [
            'billSighted.accepted' => 'Confirm you have the original bill in front of you.',
            'claimantName.required' => 'Record who is standing in front of you claiming this.',
        ]);

        $token = $petty->issue([
            'bill_no' => $this->billNo,
            'bill_date' => $this->billDate ?: null,
            'vendor_name' => $this->vendorName,
            'amount' => $this->amount,
            'claimant_name' => $this->claimantName,
            'purpose' => $this->purpose,
            'bill_sighted' => $this->billSighted,
        ], auth()->user());

        session()->flash('status', "Token {$token->serial} generated for ".Money::npr($token->amount).'.');

        $this->redirectRoute('petty-cash.show', $token, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.petty-cash.issue')->title('Issue a Token');
    }
}
