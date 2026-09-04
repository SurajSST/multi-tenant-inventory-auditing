<?php

namespace App\Livewire\Setup;

use App\Models\Location;
use App\Services\AuditLogger;
use Illuminate\View\View;
use Livewire\Component;

class Locations extends Component
{
    public string $name = '';

    public string $code = '';

    public string $note = '';

    public ?string $editingId = null;

    public function edit(string $id): void
    {
        $location = Location::findOrFail($id);

        $this->editingId = $id;
        $this->name = $location->name;
        $this->code = $location->code;
        $this->note = (string) $location->note;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'code', 'note']);
    }

    public function save(AuditLogger $audit): void
    {
        $unique = 'unique:locations,%s'.($this->editingId ? ",{$this->editingId}" : '');

        $this->validate([
            'name' => ['required', 'string', 'max:120', sprintf($unique, 'name')],
            'code' => ['required', 'string', 'max:12', sprintf($unique, 'code')],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $location = Location::updateOrCreate(
            ['id' => $this->editingId],
            ['name' => $this->name, 'code' => strtoupper($this->code), 'note' => $this->note ?: null],
        );

        $audit->record(
            action: $this->editingId ? 'LOCATION_UPDATED' : 'LOCATION_ADDED',
            entity: 'locations',
            entityId: $location->id,
            detail: ($this->editingId ? 'Block updated: ' : 'Block added: ')."{$location->name} ({$location->code})",
        );

        session()->flash('status', "{$location->name} saved.");
        $this->cancel();
    }

    public function toggle(string $id, AuditLogger $audit): void
    {
        $location = Location::findOrFail($id);
        $location->update(['is_active' => ! $location->is_active]);

        $audit->record(
            action: 'LOCATION_TOGGLED',
            entity: 'locations',
            entityId: $location->id,
            detail: "{$location->name} was ".($location->is_active ? 'reactivated' : 'retired'),
        );

        session()->flash('status', "{$location->name} ".($location->is_active ? 'reactivated' : 'retired').'.');
    }

    public function render(): View
    {
        return view('livewire.setup.locations', [
            'locations' => Location::withCount('counts')->orderBy('code')->get(),
        ])->title('Blocks and Locations');
    }
}
