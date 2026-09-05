<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\DemandStatus;
use App\Enums\Role;
use App\Models\ApprovalTier;
use App\Models\DemandApproval;
use App\Models\DemandForm;
use App\Models\User;
use App\Support\Money;
use App\Support\RefCounter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DemandService
{
    public function __construct(
        private AuditLogger $audit,
        private SettingService $settings,
        private Notifier $notify,
    ) {}

    /** @return Collection<int, ApprovalTier> */
    private function tiers(): Collection
    {
        $tiers = $this->settings->tiers();

        if ($tiers->isEmpty()) {
            throw ValidationException::withMessages([
                'tiers' => 'No approval bands are configured. Set them up first.',
            ]);
        }

        return $tiers;
    }

    /** Which band does this value ultimately have to reach? */
    public function finalTierFor(string|float|int $total): ApprovalTier
    {
        $tiers = $this->tiers();

        if (Money::lt($total, $tiers->first()->min_amount)) {
            return $tiers->first();
        }

        return $tiers->first(fn (ApprovalTier $t) => $t->covers($total)) ?? $tiers->last();
    }

    /**
     * @param  array<int, array{item_type_id?: string|null, item_name: string, quantity: int, unit_rate: string|float, specification?: string|null}>  $lines
     */
    public function create(array $lines, string $department, string $justification, User $user, ?string $needByDate = null): DemandForm
    {
        $tiers = $this->tiers();

        $prepared = collect($lines)->map(fn ($l) => [
            'item_type_id' => $l['item_type_id'] ?? null,
            'item_name' => $l['item_name'],
            'quantity' => (int) $l['quantity'],
            'unit_rate' => Money::of($l['unit_rate']),
            'line_total' => Money::mul($l['unit_rate'], (int) $l['quantity']),
            'specification' => $l['specification'] ?? null,
        ]);

        if ($prepared->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'A demand form needs at least one item.',
            ]);
        }

        $total = Money::sum($prepared->pluck('line_total'));
        $floor = $tiers->first()->min_amount;

        if (Money::lt($total, $floor)) {
            throw ValidationException::withMessages([
                'lines' => sprintf(
                    'The total is %s. Anything under %s does not go through this process.',
                    Money::npr($total),
                    Money::npr($floor),
                ),
            ]);
        }

        $finalTier = $this->finalTierFor($total);

        $demand = DB::transaction(function () use ($prepared, $department, $justification, $needByDate, $user, $total, $tiers, $finalTier) {
            ['ref' => $ref, 'fiscal_year' => $fiscalYear] = RefCounter::next('DF');

            $demand = DemandForm::create([
                'ref' => $ref,
                'fiscal_year' => $fiscalYear,
                'raised_by_id' => $user->id,
                'department' => $department,
                'justification' => $justification,
                'need_by_date' => $needByDate ?: null,
                'total_amount' => $total,
                'status' => DemandStatus::PENDING,
                // A form always enters at the bottom band and climbs, so every
                // tier below the deciding one still sees and signs it.
                'current_tier' => $tiers->first()->tier_no,
                'final_tier' => $finalTier->tier_no,
            ]);

            $demand->lines()->createMany($prepared->all());

            $this->audit->record(
                action: 'DEMAND_RAISED',
                entity: 'demand_forms',
                entityId: $demand->id,
                detail: sprintf(
                    '%s raised by %s for %s; must reach tier %d (%s)',
                    $ref,
                    $user->full_name,
                    Money::npr($total),
                    $finalTier->tier_no,
                    $finalTier->decider_label,
                ),
                actor: $user,
                after: ['ref' => $ref, 'total' => $total, 'lines' => $prepared->count()],
            );

            return $demand->load('lines');
        });

        // Outside the transaction: the form is committed and real before
        // anybody is told it exists.
        $this->notify->demandAwaitingDecision($demand);

        return $demand;
    }

    /**
     * Non-privileged users see only the forms they raised. Super Admin,
     * Accounts, Chairman and the Purchase Officer see all of them.
     */
    public function list(User $user, ?DemandStatus $status = null, bool $mineOnly = false, ?string $department = null, int $perPage = 25): LengthAwarePaginator
    {
        return DemandForm::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($department, fn ($q) => $q->where('department', $department))
            ->when($mineOnly || ! $user->seesEverything(), fn ($q) => $q->where('raised_by_id', $user->id))
            // The list shows a line count, not the lines, so it counts them in
            // SQL rather than hydrating every item on every form on the page.
            ->withCount('lines')
            ->with([
                'raisedBy:id,full_name', 'raisedBy.currentMembership',
                'approvals.actor:id,full_name', 'approvals.actor.currentMembership',
                'orders:id,ref,demand_id,status',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /** What genuinely sits with the signed-in approver, and nothing else. */
    public function myQueue(User $user): Collection
    {
        if (! $user->approval_tier) {
            return collect();
        }

        return DemandForm::query()
            ->where('status', DemandStatus::PENDING)
            ->where('current_tier', $user->approval_tier)
            // Their own requests never appear — they could not decide on them anyway.
            ->where('raised_by_id', '!=', $user->id)
            ->with([
                'raisedBy', 'raisedBy.currentMembership',
                'lines.itemType',
                'approvals.actor', 'approvals.actor.currentMembership',
            ])
            ->orderBy('created_at')
            ->get();
    }

    public function find(string $id): DemandForm
    {
        return DemandForm::with([
            'raisedBy', 'raisedBy.currentMembership',
            'lines.itemType',
            'approvals.actor', 'approvals.actor.currentMembership',
            'orders.vendor',
            'orders.orderedBy', 'orders.orderedBy.currentMembership',
            'orders.receipt.receivedBy', 'orders.receipt.receivedBy.currentMembership',
            'orders.receipt.location',
            'orders.receipt.lines.demandLine',
            'orders.bills.enteredBy', 'orders.bills.enteredBy.currentMembership',
            'orders.bills.clearedBy', 'orders.bills.clearedBy.currentMembership',
        ])->findOrFail($id);
    }

    /**
     * One decision, at one tier, by one named person. The row written here is
     * append-only and a trigger refuses it outright if the actor raised the
     * form — the check below exists only to give a readable message first.
     */
    public function decide(
        string $demandId,
        ApprovalAction $action,
        User $user,
        ?string $reason = null,
        ?string $minuteRef = null,
    ): DemandForm {
        $tiers = $this->tiers();

        $decided = DB::transaction(function () use ($demandId, $action, $user, $reason, $minuteRef, $tiers) {
            $demand = DemandForm::lockForUpdate()->findOrFail($demandId);

            if ($demand->status !== DemandStatus::PENDING) {
                throw ValidationException::withMessages([
                    'demand' => 'This form is already '.strtolower($demand->status->label()).'.',
                ]);
            }

            if ($demand->raised_by_id === $user->id) {
                throw new AuthorizationException('You raised this request, so you cannot decide on it.');
            }

            if ($user->approval_tier !== $demand->current_tier) {
                throw new AuthorizationException(sprintf(
                    'This form is sitting at tier %d. You decide at tier %s.',
                    $demand->current_tier,
                    $user->approval_tier ?: 'none',
                ));
            }

            $tier = $tiers->firstWhere('tier_no', $demand->current_tier);

            if ($action === ApprovalAction::REJECT && mb_strlen(trim((string) $reason)) < 5) {
                throw ValidationException::withMessages([
                    'reason' => 'A rejection needs a written reason.',
                ]);
            }

            if ($action === ApprovalAction::APPROVE && $tier?->requires_minute && ! trim((string) $minuteRef)) {
                throw ValidationException::withMessages([
                    'minute_ref' => 'This band is decided by the committee. Record the minute reference before approving.',
                ]);
            }

            DemandApproval::create([
                'demand_id' => $demand->id,
                'tier_no' => $demand->current_tier,
                'actor_id' => $user->id,
                'action' => $action,
                'reason' => $reason,
                'minute_ref' => $minuteRef,
            ]);

            $decidedAt = $demand->current_tier;

            if ($action === ApprovalAction::REJECT) {
                $demand->update([
                    'status' => DemandStatus::REJECTED,
                    'current_tier' => null,
                    'closed_at' => now(),
                ]);

                $this->audit->record(
                    action: 'DEMAND_REJECTED',
                    entity: 'demand_forms',
                    entityId: $demand->id,
                    detail: sprintf(
                        '%s rejected at tier %d by %s: %s',
                        $demand->ref, $decidedAt, $user->full_name, $reason,
                    ),
                    actor: $user,
                );
            } elseif ($decidedAt >= $demand->final_tier) {
                $demand->update([
                    'status' => DemandStatus::APPROVED,
                    'current_tier' => null,
                    'closed_at' => now(),
                ]);

                $this->audit->record(
                    action: 'DEMAND_APPROVED',
                    entity: 'demand_forms',
                    entityId: $demand->id,
                    detail: sprintf(
                        '%s fully approved at tier %d by %s%s',
                        $demand->ref, $decidedAt, $user->full_name,
                        $minuteRef ? " (minute {$minuteRef})" : '',
                    ),
                    actor: $user,
                );
            } else {
                $next = $tiers->first(fn ($t) => $t->tier_no > $decidedAt);

                $demand->update(['current_tier' => $next->tier_no]);

                $this->audit->record(
                    action: 'DEMAND_CLEARED_TIER',
                    entity: 'demand_forms',
                    entityId: $demand->id,
                    detail: sprintf(
                        '%s cleared tier %d by %s; now with tier %d (%s)',
                        $demand->ref, $decidedAt, $user->full_name,
                        $next->tier_no, $next->decider_label,
                    ),
                    actor: $user,
                );
            }

            return $demand->fresh();
        });

        // Everything below happens after the commit, so a decision that failed
        // to save never tells anybody it succeeded.
        $outcome = match (true) {
            $decided->status === DemandStatus::REJECTED => 'REJECTED',
            $decided->status === DemandStatus::APPROVED => 'APPROVED',
            default => 'CLEARED',
        };

        if ($outcome !== 'CLEARED') {
            $this->notify->demandDecided($decided, $outcome, $user, $reason);
        } else {
            // Still moving up the ladder: the next band is the one that now
            // has something waiting, and the raiser has nothing to act on yet.
            $this->notify->demandAwaitingDecision($decided);
        }

        return $decided;
    }

    public function cancel(string $demandId, User $user): DemandForm
    {
        return DB::transaction(function () use ($demandId, $user) {
            // Same lock decide() takes. Read without it, a form could be
            // withdrawn and approved at the same moment and the two writes
            // would land in whichever order they finished in.
            $demand = DemandForm::lockForUpdate()->findOrFail($demandId);

            if ($demand->raised_by_id !== $user->id && ! $user->hasRole(Role::SUPER_ADMIN)) {
                throw new AuthorizationException(
                    'Only the person who raised it, or the Super Admin, can withdraw it.'
                );
            }

            if ($demand->status !== DemandStatus::PENDING) {
                throw ValidationException::withMessages([
                    'demand' => 'Only a pending form can be withdrawn.',
                ]);
            }

            $demand->update([
                'status' => DemandStatus::CANCELLED,
                'current_tier' => null,
                'closed_at' => now(),
            ]);

            $this->audit->record(
                action: 'DEMAND_CANCELLED',
                entity: 'demand_forms',
                entityId: $demand->id,
                detail: "{$demand->ref} withdrawn by {$user->full_name}",
                actor: $user,
            );

            return $demand->fresh();
        });
    }
}
