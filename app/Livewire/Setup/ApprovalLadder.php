<?php

namespace App\Livewire\Setup;

use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The approval ladder. Bands must be contiguous and only the top one may be
 * open-ended — a gap would mean a rupee value that nobody in the school is
 * authorised to sign for, and the save is refused if one appears.
 */
class ApprovalLadder extends Component
{
    /** @var array<int, array{tier_no: int, min_amount: string, max_amount: string, decider_label: string, requires_minute: bool}> */
    public array $tiers = [];

    public function mount(SettingService $settings): void
    {
        $this->load($settings);
    }

    private function load(SettingService $settings): void
    {
        $this->tiers = $settings->tiers()->map(fn ($tier) => [
            'tier_no' => $tier->tier_no,
            'min_amount' => (string) $tier->min_amount,
            'max_amount' => $tier->max_amount === null ? '' : (string) $tier->max_amount,
            'decider_label' => $tier->decider_label,
            'requires_minute' => (bool) $tier->requires_minute,
        ])->values()->all();
    }

    public function addTier(): void
    {
        $last = end($this->tiers) ?: null;

        // Close off the band that was open-ended, so the new top band can take over.
        if ($last && $last['max_amount'] === '') {
            $this->tiers[array_key_last($this->tiers)]['max_amount'] =
                Money::add($last['min_amount'], 100000);
        }

        $previousMax = $last ? $this->tiers[array_key_last($this->tiers)]['max_amount'] : '0';

        $this->tiers[] = [
            'tier_no' => $last ? $last['tier_no'] + 1 : 1,
            'min_amount' => Money::add($previousMax, 1),
            'max_amount' => '',
            'decider_label' => '',
            'requires_minute' => false,
        ];
    }

    public function removeTier(int $index): void
    {
        unset($this->tiers[$index]);
        $this->tiers = array_values($this->tiers);

        // Whatever is left at the top becomes the open-ended band.
        if ($this->tiers) {
            $this->tiers[array_key_last($this->tiers)]['max_amount'] = '';
        }
    }

    public function save(SettingService $settings): void
    {
        $this->validate([
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.tier_no' => ['required', 'integer', 'min:1'],
            'tiers.*.min_amount' => ['required', 'numeric', 'min:0'],
            'tiers.*.max_amount' => ['nullable', 'numeric', 'min:0'],
            'tiers.*.decider_label' => ['required', 'string', 'max:120'],
        ], [
            'tiers.*.decider_label.required' => 'Say who decides at each band.',
        ]);

        try {
            $settings->setTiers(
                collect($this->tiers)->map(fn ($t) => [
                    'tier_no' => (int) $t['tier_no'],
                    'min_amount' => $t['min_amount'],
                    'max_amount' => $t['max_amount'] === '' ? null : $t['max_amount'],
                    'decider_label' => $t['decider_label'],
                    'requires_minute' => (bool) $t['requires_minute'],
                ])->all(),
                auth()->user(),
            );
        } catch (ValidationException $e) {
            $this->addError('ladder', $e->validator->errors()->first());

            return;
        }

        $this->load($settings);

        session()->flash('status', 'The approval ladder has been updated. It applies to demand forms raised from now on.');
    }

    public function render(): View
    {
        return view('livewire.setup.approval-ladder')->title('Approval Ladder');
    }
}
