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
        $user = auth()->user();
        $canViewAll = $user->canViewAllAuditTrail();

        if (! $canViewAll) {
            $this->actorId = $user->id;
        }

        $search = $this->search;

        $entries = AuditLog::with(['actor', 'actor.currentMembership'])
            ->when(! $canViewAll, fn ($q) => $q->where('actor_id', $user->id))
            ->when($canViewAll && $this->actorId, fn ($q) => $q->where('actor_id', $this->actorId))
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
            'canViewAll' => $canViewAll,
            // People posted to THIS school. Only higher tiers get the chooser.
            'actors' => $canViewAll
                ? User::query()
                    ->whereHas('memberships', fn ($q) => $q->where('tenant_id', app(TenantContext::class)->idOrFail()))
                    ->with('currentMembership')
                    ->orderBy('full_name')
                    ->get(['id', 'full_name'])
                : collect([$user]),
            'entities' => cache()->remember(
                $canViewAll ? 'audit_entities_'.app(TenantContext::class)->idOrFail() : 'audit_entities_user_'.$user->id,
                now()->addMinutes(10),
                fn () => ($canViewAll ? AuditLog::distinct() : AuditLog::where('actor_id', $user->id)->distinct())
                    ->orderBy('entity')
                    ->pluck('entity')
            ),
        ])->title($canViewAll ? 'Audit Trail' : 'My Activity Trail');
    }
}
