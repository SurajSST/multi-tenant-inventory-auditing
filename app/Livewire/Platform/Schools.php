<?php

namespace App\Livewire\Platform;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Tenancy\EntersTenant;
use App\Tenancy\TenantContext;
use Database\Seeders\NewSchoolSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The platform console: every school, what is in it, and the way into it.
 *
 * The counts here are the one place in the system that reads across schools on
 * purpose. Everything else is scoped, and a query that forgets to be throws
 * rather than quietly widening.
 */
class Schools extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?string $editingTenantId = null;

    public string $name = '';

    public string $slug = '';

    public string $shortName = '';

    public string $address = '';

    public string $logoUrl = '';

    public mixed $logoFile = null;

    public string $adminName = '';

    public string $adminEmail = '';

    /**
     * Start the school with a copy of the standard catalogue, or empty.
     * Defaults on, because a school that finds the codes wrong can delete them,
     * whereas one that finds the screens empty has to type 54 rows before the
     * system does anything at all.
     */
    public bool $withCatalogue = true;

    public function newSchool(): void
    {
        $this->reset(['editingTenantId', 'name', 'slug', 'shortName', 'address', 'logoUrl', 'logoFile', 'adminName', 'adminEmail', 'withCatalogue']);
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function editSchool(string $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);

        $this->editingTenantId = $tenant->id;
        $this->name = $tenant->name;
        $this->slug = $tenant->slug;
        $this->shortName = (string) $tenant->short_name;
        $this->address = (string) $tenant->address;
        $this->logoUrl = (string) $tenant->logo_url;
        $this->logoFile = null;
        $this->showForm = true;
        $this->resetErrorBag();
    }

    public function cancel(): void
    {
        $this->reset(['editingTenantId', 'showForm', 'name', 'slug', 'shortName', 'address', 'logoUrl', 'logoFile', 'adminName', 'adminEmail']);
        $this->resetErrorBag();
    }

    public function updatedName(): void
    {
        if (! $this->slug && ! $this->editingTenantId) {
            $this->slug = Str::slug($this->name);
        }
    }

    /**
     * Where the school's logo lives, once the form has been dealt with.
     *
     * An uploaded file wins. Otherwise the typed path is taken as-is, having
     * already been validated as a path on this site rather than a URL somewhere
     * else — a logo pulled from a third party would report every viewer to that
     * third party on every page load, which is not a thing to do quietly to a
     * school's staff.
     *
     * $replacing is the path being superseded; if we uploaded it ourselves, it
     * is deleted rather than left orphaned on disk.
     */
    private function processLogo(?string $replacing = null): ?string
    {
        if (! $this->logoFile) {
            return $this->logoUrl ? trim($this->logoUrl) : null;
        }

        $path = '/storage/'.$this->logoFile->store('logos', 'public');

        $this->discardUploadedLogo($replacing, $path);

        return $path;
    }

    /** Removes a logo we stored ourselves, once nothing points at it. */
    private function discardUploadedLogo(?string $old, ?string $keeping): void
    {
        if (! $old || $old === $keeping || ! str_starts_with($old, '/storage/logos/')) {
            return;
        }

        Storage::disk('public')->delete(Str::after($old, '/storage/'));
    }

    /**
     * A logo is either a path on this site or an https address.
     *
     * Hosting it elsewhere is allowed — a school may already keep its crest on
     * its own website — but the scheme is pinned to https, which rules out
     * data: blobs, javascript:, plain http and protocol-relative //host/…
     * addresses. Uploading the file is still the better option: an external
     * logo tells that host who is looking, and breaks when the host does.
     */
    private function logoPathRules(): array
    {
        // The (?!/) is load-bearing: without it "//tracker.example.com/pixel.png"
        // reads as a site path, and the browser would fetch it over whatever
        // scheme the page used, from someone else entirely.
        return ['nullable', 'string', 'max:255', 'regex:#^(https://[^\s]+|/(?!/)[^\s]*)$#'];
    }

    public function create(AuditLogger $audit): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:180', 'unique:tenants,name'],
            'slug' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:tenants,slug'],
            'shortName' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'logoUrl' => $this->logoPathRules(),
            'logoFile' => ['nullable', 'image', 'max:2048'],
            'adminName' => ['required', 'string', 'max:120'],
            'adminEmail' => ['required', 'email', 'max:180'],
        ], [
            'adminName.required' => 'Name the person who will administer this school.',
            'adminEmail.required' => 'A school with no administrator cannot be set up.',
            'logoUrl.*' => 'A logo must be a path on this site (/img/logo/school.png) or an '.
                'https address. Uploading the file is more reliable than linking to one.',
        ]);

        $finalLogoUrl = $this->processLogo();

        $tenant = DB::transaction(function () use ($finalLogoUrl) {
            $tenant = Tenant::create([
                'name' => $this->name,
                'slug' => $this->slug,
                'short_name' => $this->shortName ?: null,
                'address' => $this->address ?: null,
                'logo_url' => $finalLogoUrl,
                'is_active' => true,
            ]);

            // The catalogue, the approval ladder and the settings are seeded
            // inside the new school, so it opens with something usable rather
            // than a set of empty screens.
            app(TenantContext::class)->runFor($tenant, function () use ($tenant) {
                app(NewSchoolSeeder::class)->forSchool(
                    $tenant,
                    $this->adminName,
                    $this->adminEmail,
                    withCatalogue: $this->withCatalogue,
                );
            });

            return $tenant;
        });

        $audit->record(
            action: 'SCHOOL_CREATED',
            entity: 'tenants',
            entityId: $tenant->id,
            detail: "{$tenant->name} was set up, administered by {$this->adminName} ({$this->adminEmail})",
        );

        session()->flash('status', "{$tenant->name} is set up"
            .($this->withCatalogue ? ' with the standard catalogue' : ' with an empty register')
            .". {$this->adminName} can sign in with the default password and will be made to change it.");

        $this->cancel();
    }

    public function update(AuditLogger $audit): void
    {
        if (! $this->editingTenantId) {
            return;
        }

        $tenant = Tenant::findOrFail($this->editingTenantId);

        $this->validate([
            'name' => ['required', 'string', 'max:180', Rule::unique('tenants', 'name')->ignore($tenant->id)],
            'slug' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('tenants', 'slug')->ignore($tenant->id)],
            'shortName' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'logoUrl' => $this->logoPathRules(),
            'logoFile' => ['nullable', 'image', 'max:2048'],
        ]);

        $finalLogoUrl = $this->processLogo($tenant->logo_url) ?? $tenant->logo_url;

        $before = $tenant->only(['name', 'slug', 'short_name', 'address', 'logo_url']);

        $tenant->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'short_name' => $this->shortName ?: null,
            'address' => $this->address ?: null,
            'logo_url' => $finalLogoUrl,
        ]);

        $audit->record(
            action: 'SCHOOL_UPDATED',
            entity: 'tenants',
            entityId: $tenant->id,
            detail: "{$tenant->name} ({$tenant->slug}) details updated",
            before: $before,
            after: $tenant->only(['name', 'slug', 'short_name', 'address', 'logo_url']),
        );

        session()->flash('status', "{$tenant->name} details updated successfully.");

        $this->cancel();
    }

    public function toggleActive(string $tenantId, AuditLogger $audit): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        $tenant->update(['is_active' => ! $tenant->is_active]);

        $audit->record(
            action: $tenant->is_active ? 'SCHOOL_RESUMED' : 'SCHOOL_SUSPENDED',
            entity: 'tenants',
            entityId: $tenant->id,
            detail: $tenant->name.' was '.($tenant->is_active ? 'resumed' : 'suspended'),
        );

        session()->flash('status', $tenant->is_active
            ? $tenant->name.' is active again.'
            : $tenant->name.' is suspended. Nobody there can sign in until it is resumed.');
    }

    /** Drop into a school and work in it as the console operator. */
    public function enter(string $tenantId, EntersTenant $tenants)
    {
        $tenant = Tenant::findOrFail($tenantId);

        $tenants->enter(request(), auth()->user(), $tenant, 'entered the console for');

        return $this->redirectRoute('dashboard', navigate: false);
    }

    /**
     * One row per school, with what is actually in it.
     *
     * @return Collection<int, object>
     */
    public function summary(): Collection
    {
        return app(TenantContext::class)->runUnscoped(fn () => collect(DB::select('
            SELECT t.id, t.name, t.slug, t.short_name, t.address, t.logo_url, t.is_active, t.created_at,
                   (SELECT COUNT(*) FROM tenant_users   WHERE tenant_id = t.id AND is_active = 1) AS staff,
                   (SELECT COUNT(*) FROM item_types     WHERE tenant_id = t.id) AS items,
                   (SELECT COUNT(*) FROM demand_forms   WHERE tenant_id = t.id) AS demands,
                   (SELECT COUNT(*) FROM purchase_orders WHERE tenant_id = t.id) AS orders,
                   (SELECT COUNT(*) FROM bills          WHERE tenant_id = t.id) AS bills,
                   (SELECT COALESCE(SUM(order_amount), 0) FROM purchase_orders WHERE tenant_id = t.id) AS committed,
                   (SELECT COUNT(*) FROM bills WHERE tenant_id = t.id AND match_status = "MISMATCH") AS flagged
            FROM tenants t
            ORDER BY t.is_active DESC, t.name
        ')));
    }

    #[Computed]
    public function globalStats(): array
    {
        $schools = $this->summary();

        return [
            'total_schools' => $schools->count(),
            'active_schools' => $schools->where('is_active', 1)->count(),
            'suspended_schools' => $schools->where('is_active', 0)->count(),
            'total_staff' => (int) $schools->sum('staff'),
            'total_items' => (int) $schools->sum('items'),
            'total_demands' => (int) $schools->sum('demands'),
            'total_orders' => (int) $schools->sum('orders'),
            'total_committed' => (float) $schools->sum('committed'),
            'total_flagged' => (int) $schools->sum('flagged'),
        ];
    }

    #[Computed]
    public function recentActivity(): Collection
    {
        return app(TenantContext::class)->runUnscoped(function () {
            return AuditLog::with('actor')
                ->where(function ($query) {
                    $query->whereIn('action', ['SCHOOL_CREATED', 'SCHOOL_UPDATED', 'SCHOOL_RESUMED', 'SCHOOL_SUSPENDED', 'SETTING_CHANGED'])
                        ->orWhere('entity', 'tenants');
                })
                ->orderByDesc('at')
                ->limit(8)
                ->get();
        });
    }

    public function render(): View
    {
        return view('livewire.platform.schools', [
            'schools' => $this->summary(),
            'stats' => $this->globalStats,
            'recentActivity' => $this->recentActivity,
        ])->title('Schools · Platform Console');
    }
}
