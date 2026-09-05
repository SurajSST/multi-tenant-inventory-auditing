<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One answer per request to "which school are we in".
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // Catch N+1 queries in development, but log them rather than throwing:
        // a lazy load is a performance bug, not a reason to show school staff
        // an error page.
        Model::preventLazyLoading($this->app->isLocal());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
            Log::warning('Lazy loaded ['.$relation.'] on ['.$model::class.'] - eager load it instead.');
        });

        $this->defineGates();
    }

    /**
     * Who may do what, at the school they are currently in.
     *
     * These describe capability only — the rules that stop one person doing two
     * halves of the same transaction live in the services and, ultimately, in
     * the database.
     *
     * The platform owner is split down the middle here, and it is the most
     * important line in this file: they may READ anything at any school, and
     * administer schools and accounts, but they may not take part in a school's
     * workflow. An account that could approve a demand, place an order, verify
     * its receipt and pay the bill — at every school, unaccountably — would
     * quietly undo the separation of duties the whole system exists to enforce.
     */
    private function defineGates(): void
    {
        // Capability at the active school. A platform owner holds no posting,
        // so these are false for them unless a gate says otherwise below.
        $allow = fn (Role ...$roles) => fn (User $user) => $user->hasAnyRole($roles);

        // Reading and administering: the platform owner is included.
        $allowOrPlatform = fn (Role ...$roles) => fn (User $user) => $user->isPlatformOwner()
            || $user->hasAnyRole($roles);

        Gate::define('manage-setup', $allowOrPlatform(Role::SUPER_ADMIN));
        Gate::define('manage-staff', $allowOrPlatform(Role::SUPER_ADMIN));
        Gate::define('view-all-audit-trail', fn (User $user) => $user->canViewAllAuditTrail());
        Gate::define('view-audit-trail', fn (User $user) => $user->isPlatformOwner() || $user->activeMembership() !== null);

        // Taking part in the workflow: a school's own people, and only them.
        Gate::define('raise-demands', $allow(Role::INITIATOR, Role::SUPER_ADMIN));
        Gate::define('approve-demands', $allow(Role::APPROVER, Role::CHAIRMAN, Role::SUPER_ADMIN));
        Gate::define('place-orders', $allow(Role::PURCHASE_OFFICER, Role::SUPER_ADMIN));
        Gate::define('receive-goods', $allow(Role::RECEIVING_OFFICER, Role::SUPER_ADMIN));
        Gate::define('handle-accounts', $allow(Role::ACCOUNTS, Role::SUPER_ADMIN));
        Gate::define('enter-counts', $allow(Role::AUDITOR, Role::SUPER_ADMIN));

        // The console itself.
        Gate::define('manage-platform', fn (User $user) => $user->isPlatformOwner());
    }
}
