<?php

namespace App\Livewire\Demands;

use App\Enums\DemandStatus;
use App\Services\DemandService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $department = '';

    #[Url(except: false)]
    public bool $mine = false;

    #[\Livewire\Attributes\Computed]
    public function departments(): array
    {
        try {
            return \App\Models\DemandForm::distinct()
                ->pluck('department')
                ->filter()
                ->sort()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(DemandService $demands): View
    {
        $user = auth()->user();

        return view('livewire.demands.index', [
            'demands' => $demands->list(
                $user,
                $this->status ? DemandStatus::from($this->status) : null,
                $this->mine,
                $this->department ?: null,
            ),
            'seesEverything' => $user->seesEverything(),
        ])->title('Demand Forms');
    }
}
