<?php

namespace App\Services;

use App\Enums\TokenStatus;
use App\Models\Bill;
use App\Models\PettyCashToken;
use App\Models\User;
use App\Support\Money;
use App\Support\RefCounter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PettyCashService
{
    public function __construct(
        private AuditLogger $audit,
        private SettingService $settings,
    ) {}

    /**
     * Bill received → entered → validated against the ceiling → token generated
     * with the issuer's name and the claimant standing in front of them → routed
     * to Accounts → paid and marked settled.
     *
     * @param  array{bill_no: string, bill_date?: string|null, vendor_name: string, amount: string|float, claimant_name: string, purpose: string, bill_sighted: bool}  $data
     */
    public function issue(array $data, User $user): PettyCashToken
    {
        $ceiling = $this->settings->pettyCashCeiling();
        $amount = Money::of($data['amount']);
        $billNo = trim($data['bill_no']);

        if (Money::gt($amount, $ceiling)) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    '%s is above the petty cash ceiling of %s. This has to go through a demand form and the approval chain instead.',
                    Money::npr($amount),
                    Money::npr($ceiling),
                ),
            ]);
        }

        if (! ($data['bill_sighted'] ?? false)) {
            throw ValidationException::withMessages([
                'bill_sighted' => 'Confirm you have the original bill in front of you before issuing a token.',
            ]);
        }

        // The same bill cannot be tokenised twice, and cannot also sit in the
        // main bill register — that is the classic double-claim.
        $tokenDupe = PettyCashToken::where('bill_no', $billNo)
            ->where('status', '!=', TokenStatus::VOIDED)
            ->first();

        if ($tokenDupe) {
            throw ValidationException::withMessages([
                'bill_no' => sprintf(
                    'Token %s was already issued against bill %s on %s.',
                    $tokenDupe->serial,
                    $billNo,
                    $tokenDupe->issued_at->toDateString(),
                ),
            ]);
        }

        if (Bill::where('bill_no', $billNo)->exists()) {
            throw ValidationException::withMessages([
                'bill_no' => "Bill {$billNo} is already in the main bill register. It cannot also be claimed from petty cash.",
            ]);
        }

        return DB::transaction(function () use ($data, $user, $amount, $ceiling, $billNo) {
            ['ref' => $serial, 'fiscal_year' => $fiscalYear] = RefCounter::next('PC');

            $token = PettyCashToken::create([
                'serial' => $serial,
                'fiscal_year' => $fiscalYear,
                'bill_no' => $billNo,
                'bill_date' => $data['bill_date'] ?? null,
                'vendor_name' => $data['vendor_name'],
                'amount' => $amount,
                // Frozen for the record: changing the ceiling later never
                // retrospectively invalidates a token issued under the old one.
                'ceiling_at_issue' => $ceiling,
                'claimant_name' => $data['claimant_name'],
                'purpose' => $data['purpose'],
                'bill_sighted' => true,
                'issued_by_id' => $user->id,
            ]);

            $this->audit->record(
                action: 'TOKEN_ISSUED',
                entity: 'petty_cash_tokens',
                entityId: $token->id,
                detail: sprintf(
                    '%s issued for %s against bill %s (%s), presented by %s, sighted and issued by %s. Purpose: %s',
                    $serial,
                    Money::npr($amount),
                    $billNo,
                    $data['vendor_name'],
                    $data['claimant_name'],
                    $user->full_name,
                    $data['purpose'],
                ),
                actor: $user,
                after: ['serial' => $serial, 'amount' => $amount, 'claimant' => $data['claimant_name']],
            );

            return $token;
        });
    }

    public function list(?TokenStatus $status = null, int $perPage = 25): LengthAwarePaginator
    {
        return PettyCashToken::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['issuedBy:id,full_name', 'paidBy:id,full_name'])
            ->orderByDesc('issued_at')
            ->paginate($perPage);
    }

    public function find(string $id): PettyCashToken
    {
        return PettyCashToken::with([
            'issuedBy', 'issuedBy.currentMembership',
            'paidBy', 'paidBy.currentMembership',
        ])->findOrFail($id);
    }

    /** Whoever issued a token can never be the one who releases the payment. */
    public function markPaid(string $tokenId, User $user): PettyCashToken
    {
        return DB::transaction(function () use ($tokenId, $user) {
            return $this->settle($tokenId, $user);
        });
    }

    private function settle(string $tokenId, User $user): PettyCashToken
    {
        // Locked before it is read. Without this a double click, or two people
        // in Accounts on the same token, both saw ISSUED and both released the
        // money — the second write only overwrote the first one's name.
        $token = PettyCashToken::lockForUpdate()->findOrFail($tokenId);

        if ($token->status === TokenStatus::PAID) {
            throw ValidationException::withMessages(['token' => 'This token is already settled.']);
        }

        if ($token->status === TokenStatus::VOIDED) {
            throw ValidationException::withMessages(['token' => 'This token was voided.']);
        }

        if ($token->issued_by_id === $user->id) {
            throw ValidationException::withMessages([
                'token' => 'You issued this token. Payment must be released by somebody else in Accounts.',
            ]);
        }

        $token->update([
            'status' => TokenStatus::PAID,
            'paid_by_id' => $user->id,
            'paid_at' => now(),
        ]);

        $this->audit->record(
            action: 'TOKEN_PAID',
            entity: 'petty_cash_tokens',
            entityId: $token->id,
            detail: sprintf(
                '%s — %s paid to %s by %s',
                $token->serial,
                Money::npr($token->amount),
                $token->claimant_name,
                $user->full_name,
            ),
            actor: $user,
        );

        return $token->fresh();
    }

    /** Moves a token into the Accounts review queue. */
    public function sendToAccounts(string $tokenId, User $user): PettyCashToken
    {
        return DB::transaction(function () use ($tokenId, $user) {
            $token = PettyCashToken::lockForUpdate()->findOrFail($tokenId);

            if ($token->status !== TokenStatus::ISSUED) {
                throw ValidationException::withMessages([
                    'token' => 'Only a freshly generated token can be sent for review.',
                ]);
            }

            $token->update(['status' => TokenStatus::WITH_ACCOUNTS]);

            $this->audit->record(
                action: 'TOKEN_WITH_ACCOUNTS',
                entity: 'petty_cash_tokens',
                entityId: $token->id,
                detail: "{$token->serial} routed to Accounts for review by {$user->full_name}",
                actor: $user,
            );

            return $token->fresh();
        });
    }

    public function void(string $tokenId, string $reason, User $user): PettyCashToken
    {
        return DB::transaction(function () use ($tokenId, $reason, $user) {
            $token = PettyCashToken::lockForUpdate()->findOrFail($tokenId);

            if ($token->status === TokenStatus::PAID) {
                throw ValidationException::withMessages([
                    'token' => 'A token that has already been paid cannot be voided. Raise a correcting entry.',
                ]);
            }

            if (mb_strlen(trim($reason)) < 5) {
                throw ValidationException::withMessages([
                    'reason' => 'Voiding a token needs a written reason.',
                ]);
            }

            $token->update(['status' => TokenStatus::VOIDED, 'void_reason' => $reason]);

            $this->audit->record(
                action: 'TOKEN_VOIDED',
                entity: 'petty_cash_tokens',
                entityId: $token->id,
                detail: "{$token->serial} voided by {$user->full_name}: {$reason}",
                actor: $user,
            );

            return $token->fresh();
        });
    }

    /** The float position, for the dashboard and the petty cash screen. */
    public function summary(): array
    {
        $monthStart = now()->startOfMonth();

        $open = PettyCashToken::whereIn('status', TokenStatus::open());

        return [
            'ceiling_per_bill' => $this->settings->pettyCashCeiling(),
            'tokens_issued' => PettyCashToken::where('status', '!=', TokenStatus::VOIDED)->count(),
            'awaiting_payment' => (clone $open)->count(),
            'awaiting_payment_value' => Money::of((clone $open)->sum('amount')),
            'issued_this_month' => Money::of(
                PettyCashToken::where('issued_at', '>=', $monthStart)
                    ->where('status', '!=', TokenStatus::VOIDED)
                    ->sum('amount')
            ),
        ];
    }

    /**
     * Monthly petty cash expenditure for the last 6 months.
     *
     * @return Collection<int, array{label: string, amount: string, tokens: int, paid: int}>
     */
    public function monthlySpend(): Collection
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        // One grouped query rather than three per month. The month loop below
        // reads from the result so that a month with no tokens still appears —
        // a gap in the chart is information, and dropping the row would hide it.
        $rows = PettyCashToken::query()
            ->where('issued_at', '>=', $months->first())
            ->where('status', '!=', TokenStatus::VOIDED)
            ->selectRaw("DATE_FORMAT(issued_at, '%Y-%m') AS ym")
            ->selectRaw('COUNT(*) AS tokens')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS paid', [TokenStatus::PAID->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) AS amount', [TokenStatus::PAID->value])
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        return $months->map(function ($start) use ($rows) {
            $row = $rows->get($start->format('Y-m'));

            return [
                'label' => $start->format('M'),
                'amount' => Money::of($row->amount ?? 0),
                'tokens' => (int) ($row->tokens ?? 0),
                'paid' => (int) ($row->paid ?? 0),
            ];
        });
    }
}
