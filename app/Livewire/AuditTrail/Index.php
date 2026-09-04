<?php

namespace App\Livewire\AuditTrail;

use App\Models\AuditLog;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The full trail. Append-only at the database level, so nothing here has ever
 * been edited or removed — what was written is what happened.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $actorId = '';

    #[Url(except: '')]
    public string $entity = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['actorId', 'entity', 'search', 'from', 'to']);
        $this->resetPage();
    }

    public function render(): View
    {
        $search = $this->search;

        $entries = AuditLog::with(['actor', 'actor.currentMembership'])
            ->when($this->actorId, fn ($q) => $q->where('actor_id', $this->actorId))
            ->when($this->entity, fn ($q) => $q->where('entity', $this->entity))
            ->when($search, fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('detail', 'like', '%'.$search.'%')
                    ->orWhere('action', 'like', '%'.$search.'%');
            }))
            ->when($this->from, fn ($q) => $q->where('at', '>=', $this->from.' 00:00:00'))
            ->when($this->to, fn ($q) => $q->where('at', '<=', $this->to.' 23:59:59'))
            ->orderByDesc('at')
            ->orderByDesc('id')
            ->paginate(50);

        return view('livewire.audit-trail.index', [
            'entries' => $entries,
            // People posted to THIS school. users is deliberately global, so an
            // unfiltered list here would name every other school's staff in the
            // filter dropdown — a small leak, but a leak.
            'actors' => User::query()
                ->whereHas('memberships', fn ($q) => $q->where('tenant_id', app(TenantContext::class)->idOrFail()))
                ->with('currentMembership')
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
            'entities' => AuditLog::distinct()->orderBy('entity')->pluck('entity'),
        ])->title('Audit Trail');
    }
}
