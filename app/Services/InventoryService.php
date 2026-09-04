<?php

namespace App\Services;

use App\Enums\CountSource;
use App\Enums\Lifespan;
use App\Enums\Role;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\StockCountEntry;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private AuditLogger $audit,
        private TenantContext $tenant,
    ) {}

    /**
     * The active school's id.
     *
     * Every query in this class that goes through DB::table() or DB::select()
     * bypasses the global scope, so each one filters on this explicitly. That
     * is the whole reason these methods needed touching for tenancy — the
     * Eloquent ones did not.
     */
    private function tenantId(): string
    {
        return $this->tenant->idOrFail();
    }

    /**
     * The register exactly as the school reads it on paper: one row per item
     * type, one column per block.
     *
     * @return array{blocks: Collection<int, Location>, rows: Collection<int, array>}
     */
    public function register(?Lifespan $lifespan = null, ?string $categoryId = null, ?string $search = null): array
    {
        $blocks = Location::active()->orderBy('name')->get();

        $query = DB::table('v_stock_register')->where('tenant_id', $this->tenantId());

        if ($lifespan) {
            $query->where('lifespan', $lifespan->value);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('code_prefix', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            // Filtered in SQL, not after the fact. The view is item types
            // crossed with blocks, so filtering in PHP meant fetching every
            // combination in the school to throw most of them away.
            $query->whereIn('item_type_id', fn ($q) => $q->select('id')
                ->from('item_types')
                ->where('category_id', $categoryId));
        }

        $raw = $query->orderBy('category')->orderBy('item_name')->get();

        $rows = collect();

        foreach ($raw as $r) {
            $row = $rows->get($r->item_type_id) ?? [
                'item_type_id' => $r->item_type_id,
                'item_name' => $r->item_name,
                'code_prefix' => $r->code_prefix,
                'lifespan' => Lifespan::from($r->lifespan),
                'category' => $r->category,
                'subcategory' => $r->subcategory,
                'by_block' => [],
                'total' => 0,
                'last_counted_at' => null,
                'last_counted_by' => null,
            ];

            $row['by_block'][$r->block] = (int) $r->quantity;
            $row['total'] += (int) $r->quantity;

            if ($r->last_counted_at && (! $row['last_counted_at'] || $r->last_counted_at > $row['last_counted_at'])) {
                $row['last_counted_at'] = $r->last_counted_at;
                $row['last_counted_by'] = $r->last_counted_by;
            }

            $rows->put($r->item_type_id, $row);
        }

        return ['blocks' => $blocks, 'rows' => $rows->values()];
    }

    /** The current quantity of one item type in one block. */
    public function currentQuantity(string $itemTypeId, string $locationId): int
    {
        $row = DB::table('v_current_stock')
            ->where('tenant_id', $this->tenantId())
            ->where('item_type_id', $itemTypeId)
            ->where('location_id', $locationId)
            ->first();

        return $row ? (int) $row->quantity : 0;
    }

    /** The standing figure for every item type in one block, keyed by item type. */
    public function currentByLocation(string $locationId): array
    {
        return DB::table('v_current_stock')
            ->where('tenant_id', $this->tenantId())
            ->where('location_id', $locationId)
            ->pluck('quantity', 'item_type_id')
            ->map(fn ($q) => (int) $q)
            ->all();
    }

    /**
     * Every physical unit expanded to its own code: CHAIR.S.1, CHAIR.S.2 …
     *
     * Numbering runs across all blocks in block order, exactly the way the
     * school's original LIST_OF_INV sheet is written.
     *
     * @return array{item_type: ItemType, total: int, units: Collection<int, array>}
     */
    public function unitCodes(string $itemTypeId): array
    {
        $item = ItemType::findOrFail($itemTypeId);

        $stock = DB::table('v_current_stock as cs')
            ->join('locations as l', 'l.id', '=', 'cs.location_id')
            ->where('cs.tenant_id', $this->tenantId())
            ->where('cs.item_type_id', $itemTypeId)
            ->where('cs.quantity', '>', 0)
            ->orderBy('l.name')
            ->get(['l.name as block', 'cs.quantity', 'cs.last_counted_at']);

        $units = collect();
        $n = 0;

        foreach ($stock as $s) {
            for ($i = 0; $i < (int) $s->quantity; $i++) {
                $n++;
                $units->push([
                    'unit_code' => "{$item->code_prefix}.{$n}",
                    'unit_no' => $n,
                    'block' => $s->block,
                ]);
            }
        }

        return ['item_type' => $item, 'total' => $n, 'units' => $units];
    }

    /**
     * Auditors only. Every changed line becomes a new append-only ledger row —
     * the previous figure is never overwritten, so a correction adds to the
     * history rather than erasing it.
     *
     * Lines whose figure has not moved are skipped, so the ledger stays quiet
     * and a re-count of an unchanged block adds no noise.
     *
     * @param  array<int, array{item_type_id: string, location_id: string, quantity: int, note?: string|null}>  $lines
     * @return Collection<int, StockCountEntry>
     */
    public function submitCount(array $lines, User $auditor, ?CountSource $source = null, ?string $note = null): Collection
    {
        if (! $auditor->hasAnyRole([Role::AUDITOR, Role::SUPER_ADMIN])) {
            throw new AuthorizationException(
                'Only an auditor assigned in Setup can enter physical counts.'
            );
        }

        // An auditor may be scoped to particular blocks.
        $scope = $auditor->scopedLocationIds();

        if ($scope) {
            $outside = collect($lines)->reject(fn ($l) => in_array($l['location_id'], $scope, true));

            if ($outside->isNotEmpty()) {
                throw new AuthorizationException(
                    'You are assigned to specific blocks only. One or more lines fall outside them.'
                );
            }
        }

        $source ??= CountSource::PHYSICAL_AUDIT;

        return DB::transaction(function () use ($lines, $auditor, $source, $note) {
            $written = collect();

            // One read for the whole sheet. This used to ask the database for
            // the standing figure once per line, which on a fifty-item block
            // meant fifty round trips against a window-function view before a
            // single row was written.
            $standing = collect($lines)
                ->pluck('location_id')
                ->unique()
                ->mapWithKeys(fn ($locationId) => [$locationId => $this->currentByLocation($locationId)])
                ->all();

            $itemTypeIds = collect($lines)->pluck('item_type_id')->unique()->all();

            $this->lockLedgerFor($itemTypeIds);

            $items = ItemType::whereIn('id', $itemTypeIds)->pluck('name', 'id');
            $blocks = Location::whereIn('id', array_keys($standing))->pluck('name', 'id');

            foreach ($lines as $line) {
                $previous = $standing[$line['location_id']][$line['item_type_id']] ?? 0;

                if ($previous === (int) $line['quantity']) {
                    continue; // Nothing moved. No noise in the ledger.
                }

                $entry = StockCountEntry::create([
                    'item_type_id' => $line['item_type_id'],
                    'location_id' => $line['location_id'],
                    'quantity' => (int) $line['quantity'],
                    'previous_qty' => $previous,
                    'source' => $source,
                    'note' => $line['note'] ?? $note,
                    'counted_by_id' => $auditor->id,
                ]);

                $written->push($entry);

                $this->audit->record(
                    action: 'COUNT_ENTERED',
                    entity: 'stock_count_entries',
                    entityId: $entry->id,
                    detail: sprintf(
                        '%s at %s: %d to %d, counted by %s',
                        $items[$line['item_type_id']] ?? 'Unknown item',
                        $blocks[$line['location_id']] ?? 'Unknown block',
                        $previous,
                        $entry->quantity,
                        $auditor->full_name,
                    ),
                    actor: $auditor,
                    before: ['quantity' => $previous],
                    after: ['quantity' => $entry->quantity],
                );
            }

            return $written;
        });
    }

    /**
     * Serialises everything that writes to the ledger for these item types.
     *
     * Current stock is derived from the newest row, so a writer has to read the
     * standing figure before it can add to it. Two receipts landing on the same
     * item and block at the same moment both read the same figure and the
     * second one silently wrote the first one's units out of existence. Locking
     * the item_types rows — which always exist, unlike the ledger row being
     * derived — makes those two writers queue instead. Ordered by id so two
     * batches touching the same items cannot deadlock against each other.
     *
     * @param  array<int, string>  $itemTypeIds
     */
    private function lockLedgerFor(array $itemTypeIds): void
    {
        if ($itemTypeIds === []) {
            return;
        }

        ItemType::whereIn('id', $itemTypeIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /**
     * Post goods into the ledger when a receipt is verified. Separate from
     * submitCount() because this is not an auditor's physical count — it is the
     * system recording what arrived.
     *
     * Callers are already inside a transaction (OrderService::receive), which is
     * what makes the lock below hold for the length of the receipt.
     */
    public function postReceipt(
        string $itemTypeId,
        string $locationId,
        int $quantity,
        User $receiver,
        string $receiptId,
        string $note,
    ): StockCountEntry {
        $this->lockLedgerFor([$itemTypeId]);

        $previous = $this->currentQuantity($itemTypeId, $locationId);

        return StockCountEntry::create([
            'item_type_id' => $itemTypeId,
            'location_id' => $locationId,
            'quantity' => $previous + $quantity,
            'previous_qty' => $previous,
            'source' => CountSource::GOODS_RECEIPT,
            'reference_id' => $receiptId,
            'note' => $note,
            'counted_by_id' => $receiver->id,
        ]);
    }

    /** Written-off, transferred or corrected stock — always a new ledger row. */
    public function adjust(string $itemTypeId, string $locationId, int $quantity, User $actor, string $reason): StockCountEntry
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'An adjustment needs a written reason.',
            ]);
        }

        return DB::transaction(function () use ($itemTypeId, $locationId, $quantity, $actor, $reason) {
            $this->lockLedgerFor([$itemTypeId]);

            $previous = $this->currentQuantity($itemTypeId, $locationId);

            $entry = StockCountEntry::create([
                'item_type_id' => $itemTypeId,
                'location_id' => $locationId,
                'quantity' => $quantity,
                'previous_qty' => $previous,
                'source' => CountSource::ADJUSTMENT,
                'note' => $reason,
                'counted_by_id' => $actor->id,
            ]);

            $entry->load(['itemType', 'location']);

            $this->audit->record(
                action: 'STOCK_ADJUSTED',
                entity: 'stock_count_entries',
                entityId: $entry->id,
                detail: sprintf(
                    '%s at %s adjusted %d to %d by %s: %s',
                    $entry->itemType->name,
                    $entry->location->name,
                    $previous,
                    $quantity,
                    $actor->full_name,
                    $reason,
                ),
                actor: $actor,
                before: ['quantity' => $previous],
                after: ['quantity' => $quantity],
            );

            return $entry;
        });
    }

    /**
     * The difference between what the school bought through this system and
     * what is physically there.
     */
    public function variance(?string $locationId = null): Collection
    {
        $sql = '
            SELECT it.name AS item, it.code_prefix, l.name AS block, l.id AS location_id,
                   COALESCE(r.purchased, 0) AS purchased_through_system,
                   COALESCE(cs.quantity, 0) AS physically_counted,
                   COALESCE(cs.quantity, 0) - COALESCE(r.purchased, 0) AS variance,
                   cs.last_counted_at
            FROM item_types it
            CROSS JOIN locations l
            LEFT JOIN (
              SELECT dl.item_type_id, gr.location_id, SUM(grl.qty_received) AS purchased
              FROM goods_receipt_lines grl
              JOIN goods_receipts gr ON gr.id = grl.receipt_id
              JOIN demand_lines dl   ON dl.id = grl.demand_line_id
              WHERE dl.item_type_id IS NOT NULL AND grl.tenant_id = ?
              GROUP BY dl.item_type_id, gr.location_id
            ) r ON r.item_type_id = it.id AND r.location_id = l.id
            LEFT JOIN v_current_stock cs ON cs.item_type_id = it.id AND cs.location_id = l.id
            WHERE it.is_active = 1 AND l.is_active = 1
              -- Both sides of the cross join have to be the same school. Without
              -- this the report tells one school it is missing stock that in fact
              -- belongs to a different school entirely.
              AND it.tenant_id = ? AND l.tenant_id = it.tenant_id
              AND (COALESCE(r.purchased, 0) > 0 OR COALESCE(cs.quantity, 0) > 0)
              AND (? IS NULL OR l.id = ?)
            ORDER BY ABS(COALESCE(cs.quantity, 0) - COALESCE(r.purchased, 0)) DESC, it.name
        ';

        $tenantId = $this->tenantId();

        return collect(DB::select($sql, [$tenantId, $tenantId, $locationId, $locationId]));
    }

    /** Full history for one item type in one block, newest first. */
    public function history(string $itemTypeId, ?string $locationId = null): Collection
    {
        return StockCountEntry::query()
            ->where('item_type_id', $itemTypeId)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId))
            ->with(['countedBy', 'countedBy.currentMembership', 'location', 'itemType'])
            ->orderByDesc('counted_at')
            ->orderByDesc('id')
            ->get();
    }

    /** Anything that has fallen to or below its reorder level. */
    public function lowStock(): Collection
    {
        return collect(DB::select('
            SELECT it.id, it.name, it.code_prefix, it.reorder_level,
                   COALESCE(SUM(cs.quantity), 0) AS on_hand
            FROM item_types it
            LEFT JOIN v_current_stock cs ON cs.item_type_id = it.id
            WHERE it.is_active = 1 AND it.reorder_level IS NOT NULL
              AND it.tenant_id = ?
            GROUP BY it.id, it.name, it.code_prefix, it.reorder_level
            HAVING COALESCE(SUM(cs.quantity), 0) <= it.reorder_level
            ORDER BY on_hand ASC
        ', [$this->tenantId()]));
    }
}
