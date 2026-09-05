<?php

namespace App\Livewire\PettyCash;

use App\Models\TenantUser;
use App\Models\Vendor;
use App\Services\PettyCashService;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Support\Collection;
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

    public string $vendorSelect = '';

    public string $customVendor = '';

    public string $amount = '';

    public string $claimantName = '';

    public string $claimantSelect = '';

    public string $customClaimant = '';

    public string $purpose = '';

    public string $purposeSelect = '';

    public bool $billSighted = false;

    #[Computed]
    public function vendors(): Collection
    {
        return Vendor::query()->orderBy('name')->get();
    }

    #[Computed]
    public function staffMembers(): Collection
    {
        return TenantUser::with('user')
            ->where('is_active', true)
            ->get()
            ->sortBy('user.full_name')
            ->values();
    }

    #[Computed]
    public function commonPurposes(): array
    {
        return [
            'Classroom & teaching supplies',
            'Emergency lab & science materials',
            'Office stationery & printing paper',
            'Drinking water & tea refreshments',
            'Cleaning & sanitation supplies',
            'Hardware & electrical maintenance',
            'Courier, postage & transportation',
            'First aid & medical consumables',
            'Event & sports supplies',
        ];
    }

    public function updatedVendorSelect(string $val): void
    {
        if ($val === 'OTHER') {
            $this->vendorName = trim($this->customVendor);
        } else {
            $this->vendorName = $val;
        }
    }

    public function updatedCustomVendor(string $val): void
    {
        if ($this->vendorSelect === 'OTHER') {
            $this->vendorName = trim($val);
        }
    }

    public function updatedClaimantSelect(string $val): void
    {
        if ($val === 'OTHER') {
            $this->claimantName = trim($this->customClaimant);
        } else {
            $this->claimantName = $val;
        }
    }

    public function updatedCustomClaimant(string $val): void
    {
        if ($this->claimantSelect === 'OTHER') {
            $this->claimantName = trim($val);
        }
    }

    public function updatedPurposeSelect(string $val): void
    {
        if ($val && $val !== 'OTHER') {
            $this->purpose = $val;
        }
    }

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
        if ($this->vendorSelect === 'OTHER') {
            $this->vendorName = trim($this->customVendor);
        } elseif ($this->vendorSelect !== '') {
            $this->vendorName = trim($this->vendorSelect);
        }

        if ($this->claimantSelect === 'OTHER') {
            $this->claimantName = trim($this->customClaimant);
        } elseif ($this->claimantSelect !== '') {
            $this->claimantName = trim($this->claimantSelect);
        }

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
