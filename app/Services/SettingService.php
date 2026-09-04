<?php

namespace App\Services;

use App\Models\ApprovalTier;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettingService
{
    public const PETTY_CASH_CEILING = 'petty_cash_ceiling';

    public const SCHOOL_NAME = 'school_name';

    public const ALLOW_ORDER_ABOVE_APPROVAL = 'allow_order_above_approval';

    private array $memoSettings = [];

    private ?Collection $memoTiers = null;

    public function __construct(private AuditLogger $audit) {}

    public function get(string $key, mixed $fallback = null): mixed
    {
        if (array_key_exists($key, $this->memoSettings)) {
            return $this->memoSettings[$key] ?? $fallback;
        }

        $row = AppSetting::where('key', $key)->first();
        $this->memoSettings[$key] = $row ? $row->value : null;

        return $this->memoSettings[$key] ?? $fallback;
    }

    public function pettyCashCeiling(): string
    {
        return Money::of($this->get(self::PETTY_CASH_CEILING, 15000));
    }

    public function schoolName(): string
    {
        return (string) $this->get(self::SCHOOL_NAME, 'Prativa Secondary School');
    }

    public function allowOrderAboveApproval(): bool
    {
        return (bool) $this->get(self::ALLOW_ORDER_ABOVE_APPROVAL, true);
    }

    public function set(string $key, mixed $value, ?User $actor = null): AppSetting
    {
        $before = AppSetting::where('key', $key)->first()?->value;

        $setting = AppSetting::updateOrCreate(['key' => $key], ['value' => $value]);

        $this->memoSettings[$key] = $value;

        $this->audit->record(
            action: 'SETTING_CHANGED',
            entity: 'app_settings',
            entityId: $key,
            detail: sprintf(
                '%s changed from %s to %s',
                $key,
                json_encode($before),
                json_encode($value),
            ),
            actor: $actor,
            before: ['value' => $before],
            after: ['value' => $value],
        );

        return $setting;
    }

    /** @return Collection<int, ApprovalTier> */
    public function tiers(): Collection
    {
        if ($this->memoTiers !== null) {
            return $this->memoTiers;
        }

        return $this->memoTiers = ApprovalTier::active()->orderBy('tier_no')->get();
    }

    /**
     * Replaces the whole approval ladder in one go, after checking that the
     * bands neither overlap nor leave a gap. A gap would mean a rupee value
     * that nobody in the school is authorised to sign for.
     *
     * @param  array<int, array{tier_no:int, min_amount:string|float, max_amount:string|float|null, decider_label:string, requires_minute?:bool}>  $tiers
     */
    public function setTiers(array $tiers, ?User $actor = null): Collection
    {
        $sorted = collect($tiers)->sortBy('tier_no')->values();

        if ($sorted->isEmpty()) {
            throw ValidationException::withMessages([
                'tiers' => 'At least one approval band is needed.',
            ]);
        }

        foreach ($sorted as $i => $tier) {
            $max = $tier['max_amount'] ?? null;
            $label = 'Tier '.$tier['tier_no'];

            if ($max !== null && Money::lte($max, $tier['min_amount'])) {
                throw ValidationException::withMessages([
                    'tiers' => "{$label}: the upper limit must be above the lower one.",
                ]);
            }

            $next = $sorted->get($i + 1);

            if (! $next) {
                continue;
            }

            if ($max === null) {
                throw ValidationException::withMessages([
                    'tiers' => 'Only the highest band may be open-ended.',
                ]);
            }

            // Bands must be contiguous: the next one starts one rupee up.
            if (! Money::eq($next['min_amount'], Money::add($max, 1))) {
                throw ValidationException::withMessages([
                    'tiers' => sprintf(
                        '%s ends at %s but tier %d starts at %s. That leaves a value nobody is authorised to sign.',
                        $label,
                        Money::npr($max),
                        $next['tier_no'],
                        Money::npr($next['min_amount']),
                    ),
                ]);
            }
        }

        $before = $this->tiers()->map(fn ($t) => $t->only([
            'tier_no', 'min_amount', 'max_amount', 'decider_label', 'requires_minute',
        ]))->all();

        $keep = $sorted->pluck('tier_no')->all();

        // The whole ladder moves or none of it does. Halfway through this loop
        // the bands are neither the old set nor the new one, and a demand form
        // raised in that window would be routed against a ladder with a hole in it.
        DB::transaction(function () use ($sorted, $keep) {
            // Bands that are gone are deactivated, never deleted — historical
            // approvals still point at their tier number.
            ApprovalTier::whereNotIn('tier_no', $keep)->update(['is_active' => false]);

            foreach ($sorted as $tier) {
                ApprovalTier::updateOrCreate(
                    ['tier_no' => $tier['tier_no']],
                    [
                        'min_amount' => Money::of($tier['min_amount']),
                        'max_amount' => $tier['max_amount'] === null ? null : Money::of($tier['max_amount']),
                        'decider_label' => $tier['decider_label'],
                        'requires_minute' => (bool) ($tier['requires_minute'] ?? false),
                        'is_active' => true,
                    ]
                );
            }
        });

        $this->memoTiers = null;
        $after = $this->tiers();

        $this->audit->record(
            action: 'APPROVAL_LADDER_CHANGED',
            entity: 'approval_tiers',
            entityId: null,
            detail: 'The approval ladder was rewritten: '.$after
                ->map(fn ($t) => "tier {$t->tier_no} {$t->range()} — {$t->decider_label}")
                ->implode('; '),
            actor: $actor,
            before: $before,
            after: $after->map(fn ($t) => $t->only([
                'tier_no', 'min_amount', 'max_amount', 'decider_label', 'requires_minute',
            ]))->all(),
        );

        return $after;
    }
}
