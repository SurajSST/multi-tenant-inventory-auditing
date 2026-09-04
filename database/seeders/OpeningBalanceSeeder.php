<?php

namespace Database\Seeders;

use App\Enums\CountSource;
use App\Enums\Role;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\StockCountEntry;
use App\Models\TenantUser;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class OpeningBalanceSeeder extends Seeder
{
    /**
     * Opening balances migrated from LIST_OF_INV: [code prefix, A, B, C, D, E, F]
     *
     * THESE ARE SAMPLE FIGURES carried over from the reference system. Replace
     * them with the auditor's actual count before go-live, or the variance
     * report compares real purchases against invented stock and means nothing.
     */
    private const OPENING = [
        ['2024.3T', 12, 10, 8, 0, 6, 0],
        ['2026.3T', 0, 0, 14, 12, 0, 0],
        ['2024.2T', 9, 11, 0, 0, 7, 4],
        ['2026.2T', 6, 0, 10, 0, 0, 0],
        ['MONT.2T', 0, 0, 0, 0, 9, 6],
        ['CHAIR.S', 20, 16, 12, 10, 8, 6],
        ['CHAIR.J', 14, 18, 10, 0, 0, 0],
        ['HC', 0, 0, 0, 40, 0, 0],
        ['OC', 6, 4, 3, 2, 2, 1],
        ['OT', 4, 3, 2, 2, 1, 1],
        ['CS', 0, 0, 0, 0, 25, 0],
        ['CS.O', 0, 0, 0, 0, 16, 0],
        ['A.C', 3, 2, 2, 1, 1, 0],
        ['LAPTOP', 2, 1, 1, 0, 1, 0],
        ['P', 1, 1, 1, 1, 0, 0],
        ['WB', 8, 6, 5, 4, 3, 2],
        ['S.B', 2, 2, 1, 1, 0, 0],
        ['PJCTR', 3, 2, 2, 0, 0, 0],
        ['CF', 10, 8, 6, 4, 3, 2],
        ['CCTV.C', 6, 5, 4, 3, 3, 2],
        ['HBB', 0, 0, 0, 0, 0, 48],
    ];

    public function run(): void
    {
        // stock_count_entries is append-only, so this seeder must not run twice
        // against the same database — there is no way to undo an entry.
        if (StockCountEntry::where('source', CountSource::OPENING_BALANCE)->exists()) {
            $this->command?->warn('  Opening balances are already on the ledger — skipped.');

            return;
        }

        // The auditor posted to THIS school, not a hardcoded address. Every
        // ledger row has to be credited to somebody who actually works here —
        // the database refuses it otherwise.
        $auditor = $this->auditorForThisSchool();
        $items = ItemType::pluck('id', 'code_prefix');
        $blocks = Location::orderBy('code')->pluck('id', 'code');
        $codes = ['A', 'B', 'C', 'D', 'E', 'F'];

        foreach (self::OPENING as $row) {
            $prefix = array_shift($row);
            $itemTypeId = $items[$prefix] ?? null;

            if (! $itemTypeId) {
                continue;
            }

            foreach ($row as $i => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                StockCountEntry::create([
                    'item_type_id' => $itemTypeId,
                    'location_id' => $blocks[$codes[$i]],
                    'quantity' => $quantity,
                    'previous_qty' => 0,
                    'source' => CountSource::OPENING_BALANCE,
                    'note' => 'Migrated from LIST_OF_INV — replace with the auditor’s real count',
                    'counted_by_id' => $auditor->id,
                ]);
            }
        }
    }

    /**
     * Whoever holds the Auditor posting at the school being seeded. Falls back
     * to the Super Admin, since a school always has one of those.
     */
    private function auditorForThisSchool(): User
    {
        $tenantId = app(TenantContext::class)->idOrFail();

        $withRole = fn (string $role) => TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereHas('roleRows', fn ($q) => $q->where('role', $role))
            ->with('user')
            ->first();

        $membership = $withRole(Role::AUDITOR->value)
            ?? $withRole(Role::SUPER_ADMIN->value);

        return $membership->user;
    }
}
