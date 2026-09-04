<?php

namespace App\Livewire\PettyCash;

use App\Models\PettyCashToken;
use App\Services\PettyCashService;
use Illuminate\View\View;
use Livewire\Component;

/** The token itself, laid out so it can be printed and handed over. */
class Show extends Component
{
    public string $tokenId;

    public function mount(PettyCashToken $token): void
    {
        $this->tokenId = $token->id;
    }

    public function pay(PettyCashService $petty): void
    {
        $token = $petty->markPaid($this->tokenId, auth()->user());

        session()->flash('status', "{$token->serial} settled.");
    }

    public function render(PettyCashService $petty): View
    {
        $token = $petty->find($this->tokenId);

        return view('livewire.petty-cash.show', ['token' => $token])->title($token->serial);
    }
}
