<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Bill;
use App\Models\DemandForm;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BillFlagged;
use App\Notifications\DemandAwaitingYou;
use App\Notifications\DemandDecided;
use App\Notifications\GoodsReceived;
use App\Notifications\SchoolNotification;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Telling the right people, and nobody else.
 *
 * All the "who should hear about this" logic sits here rather than in the
 * services, so a service still reads as the business rule it enforces and this
 * class can be read on its own to answer "who gets told what".
 *
 * Two rules hold throughout:
 *
 *   Nobody is told about their own action. You know what you just did; being
 *   emailed about it is noise, and noise is what makes people stop reading
 *   notifications at all.
 *
 *   A failure to notify never fails the work. The demand was approved; that is
 *   a fact in the database. If the mail server is down, that is a problem with
 *   the mail server, not a reason to roll back an approval.
 */
class Notifier
{
    public function __construct(private TenantContext $tenant) {}

    // ── the approval ladder ──────────────────────────────────

    /** Everyone whose band the form has just arrived at. */
    public function demandAwaitingDecision(DemandForm $demand): void
    {
        if (! $demand->current_tier) {
            return;
        }

        $demand->loadMissing('raisedBy');

        $this->send(
            // Never the person who raised it: they cannot decide on their own
            // form, so telling them it needs a decision would be a lie.
            $this->postedAtTier($demand->current_tier)->except([$demand->raised_by_id]),
            fn (Tenant $t) => DemandAwaitingYou::for($t, $demand),
        );
    }

    /** The person who raised it, once somebody has decided. */
    public function demandDecided(DemandForm $demand, string $outcome, User $decidedBy, ?string $reason = null): void
    {
        if ($demand->raised_by_id === $decidedBy->id) {
            return;
        }

        $this->send(
            collect([$demand->raisedBy]),
            fn (Tenant $t) => new DemandDecided(
                $t, $demand->id, $demand->ref, $outcome, $decidedBy->full_name, $reason,
            ),
        );
    }

    // ── goods and money ──────────────────────────────────────

    /** The person who placed the order, once somebody else has checked it in. */
    public function goodsReceived(PurchaseOrder $order, GoodsReceipt $receipt): void
    {
        $receipt->loadMissing('receivedBy');

        $this->send(
            collect([$order->orderedBy]),
            fn (Tenant $t) => new GoodsReceived(
                $t,
                $order->id,
                $order->ref,
                $receipt->receivedBy->full_name,
                $receipt->condition->value ?? (string) $receipt->condition,
                $receipt->discrepancy_note,
            ),
        );
    }

    /** Accounts and the Super Admin, when a bill does not match. */
    public function billFlagged(Bill $bill, User $enteredBy): void
    {
        $bill->loadMissing('vendor');

        $this->send(
            $this->postedWithRole(Role::ACCOUNTS, Role::SUPER_ADMIN)->except([$enteredBy->id]),
            fn (Tenant $t) => new BillFlagged(
                $t,
                $bill->bill_no,
                $bill->vendor?->name ?? 'the vendor',
                Money::npr($bill->bill_amount),
                Money::npr($bill->ordered_amount),
                Money::npr($bill->variance_amount),
                $enteredBy->full_name,
            ),
        );
    }

    // ── who ──────────────────────────────────────────────────

    /**
     * Everyone at this school who decides at a given band.
     *
     * tenant_users has no global scope — the login has to read it before a
     * school is active — so the tenant filter here is doing real work.
     *
     * @return Collection<string, User>
     */
    private function postedAtTier(int $tierNo): Collection
    {
        return User::query()
            ->whereHas('memberships', fn ($q) => $q
                ->where('tenant_id', $this->tenant->idOrFail())
                ->where('is_active', true)
                ->where('approval_tier', $tierNo))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');
    }

    /** @return Collection<string, User> */
    private function postedWithRole(Role ...$roles): Collection
    {
        $wanted = array_map(fn (Role $r) => $r->value, $roles);

        return User::query()
            ->whereHas('memberships', fn ($q) => $q
                ->where('tenant_id', $this->tenant->idOrFail())
                ->where('is_active', true)
                ->whereHas('roleRows', fn ($r) => $r->whereIn('role', $wanted)))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');
    }

    // ── sending ──────────────────────────────────────────────

    /**
     * @param  Collection<int|string, User|null>  $recipients
     * @param  callable(Tenant): SchoolNotification  $build
     */
    private function send(Collection $recipients, callable $build): void
    {
        $people = $recipients->filter()->unique('id')->values();

        if ($people->isEmpty()) {
            return;
        }

        try {
            $tenant = $this->tenant->current();

            if (! $tenant) {
                return;
            }

            Notification::send($people, $build($tenant));
        } catch (Throwable $e) {
            // The work itself already succeeded and is committed. Losing a
            // notification is worth a log line, never an exception thrown back
            // at somebody who has just correctly approved a purchase.
            Log::warning('Could not send a notification: '.$e->getMessage(), [
                'exception' => $e,
                'recipients' => $people->pluck('id')->all(),
            ]);
        }
    }
}
