<?php

namespace App\Services;

use App\Enums\DemandStatus;
use App\Enums\Lifespan;
use App\Enums\MatchStatus;
use App\Enums\TokenStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\DemandForm;
use App\Models\PettyCashToken;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        private InventoryService $inventory,
        private TenantContext $tenant,
    ) {}

    /**
     * Every query below is raw, so the global scope does not reach it. Each one
     * filters on this instead. A report that quietly summed two schools together
     * would not look like a bug — it would look like a bigger school.
     */
    private function tenantId(): string
    {
        return $this->tenant->idOrFail();
    }

    public function dashboard(): array
    {
        return [
            'durable_units' => $this->unitsOnRegister(Lifespan::DURABLE),
            'consumable_units' => $this->unitsOnRegister(Lifespan::CONSUMABLE),
            'pending_approvals' => DemandForm::where('status', DemandStatus::PENDING)->count(),
            'bills_entered' => Bill::count(),
            'total_billed' => Money::of(Bill::sum('bill_amount')),
            'bill_mismatches' => Bill::where('match_status', MatchStatus::MISMATCH)->count(),
            'open_tokens' => PettyCashToken::whereIn('status', TokenStatus::open())->count(),
            'awaiting_order' => DemandForm::where('status', DemandStatus::APPROVED)
                ->whereDoesntHave('orders')->count(),
            'by_category' => $this->stockByCategory(),
            'low_stock' => $this->inventory->lowStock(),
            'recent_activity' => AuditLog::with(['actor', 'actor.currentMembership'])->orderByDesc('at')->limit(15)->get(),
        ];
    }

    /**
     * Committed procurement spend for the last six calendar months, one row
     * per month including months with nothing ordered, so the dashboard chart
     * always shows a full run rather than skipping quiet months.
     */
    public function monthlySpend(int $months = 6): Collection
    {
        $rows = DB::select('
            SELECT DATE_FORMAT(ordered_at, "%Y-%m") AS month,
                   COALESCE(SUM(order_amount), 0)    AS amount,
                   COUNT(*)                          AS orders
            FROM purchase_orders
            WHERE tenant_id = ? AND ordered_at >= ?
            GROUP BY month
        ', [$this->tenantId(), now()->subMonths($months - 1)->startOfMonth()]);

        $byMonth = collect($rows)->keyBy('month');

        return collect(range($months - 1, 0))->map(function ($back) use ($byMonth) {
            $date = now()->subMonths($back);
            $key = $date->format('Y-m');
            $row = $byMonth->get($key);

            return [
                'label' => $date->format('M'),
                'amount' => Money::of($row->amount ?? 0),
                'orders' => (int) ($row->orders ?? 0),
            ];
        })->values();
    }

    private function unitsOnRegister(Lifespan $lifespan): int
    {
        $row = DB::selectOne('
            SELECT COALESCE(SUM(cs.quantity), 0) AS n
            FROM v_current_stock cs
            JOIN item_types it ON it.id = cs.item_type_id
            WHERE it.tenant_id = ? AND it.lifespan = ?
        ', [$this->tenantId(), $lifespan->value]);

        return (int) ($row->n ?? 0);
    }

    public function stockByCategory(): Collection
    {
        return collect(DB::select('
            SELECT c.name AS category,
                   COUNT(DISTINCT it.id)         AS item_types,
                   COALESCE(SUM(cs.quantity), 0) AS units_on_register
            FROM categories c
            LEFT JOIN item_types it      ON it.category_id = c.id AND it.is_active = 1
            LEFT JOIN v_current_stock cs ON cs.item_type_id = it.id
            WHERE c.is_active = 1 AND c.tenant_id = ?
            GROUP BY c.id, c.name, c.sort_order
            ORDER BY c.sort_order, c.name
        ', [$this->tenantId()]));
    }

    /**
     * Spend broken down the way management asks for it: category, then subcategory.
     *
     * The value and the units are summed in one pass and the bill count in
     * another, then the two are joined. Counted in a single query, a demand
     * line that had two bills against it was added to the approved value twice
     * and its received units doubled with it — the report read Rs. 1,20,000
     * approved where Rs. 60,000 had actually been signed for.
     */
    public function spendByCategory(?string $from = null, ?string $to = null): Collection
    {
        $window = 'd.tenant_id = ? AND (? IS NULL OR d.created_at >= ?) AND (? IS NULL OR d.created_at <= ?)';
        $classify = '
            JOIN demand_forms d       ON d.id = dl.demand_id AND d.status = "APPROVED"
            LEFT JOIN item_types it   ON it.id = dl.item_type_id
            LEFT JOIN categories c    ON c.id = it.category_id
            LEFT JOIN subcategories s ON s.id = it.subcategory_id';

        return collect(DB::select('
            SELECT v.category,
                   v.subcategory,
                   COALESCE(bc.bills, 0) AS bills,
                   v.approved_value,
                   v.units_received
            FROM (
              SELECT COALESCE(c.name, "Unassigned") AS category,
                     COALESCE(s.name, "Unassigned") AS subcategory,
                     COALESCE(SUM(dl.line_total), 0) AS approved_value,
                     COALESCE(SUM(rec.qty), 0)       AS units_received
              FROM demand_lines dl'.$classify.'
              LEFT JOIN (
                SELECT demand_line_id, SUM(qty_received) AS qty
                FROM goods_receipt_lines GROUP BY demand_line_id
              ) rec ON rec.demand_line_id = dl.id
              WHERE '.$window.'
              GROUP BY c.name, s.name
            ) v
            LEFT JOIN (
              SELECT COALESCE(c.name, "Unassigned") AS category,
                     COALESCE(s.name, "Unassigned") AS subcategory,
                     COUNT(DISTINCT b.id) AS bills
              FROM demand_lines dl'.$classify.'
              JOIN purchase_orders po ON po.demand_id = d.id
              JOIN bills b            ON b.purchase_order_id = po.id
              WHERE '.$window.'
              GROUP BY c.name, s.name
            ) bc ON bc.category = v.category AND bc.subcategory = v.subcategory
            ORDER BY v.category = "Unassigned", v.category, v.subcategory
        ', [
            $this->tenantId(), $from, $from, $to, $to,
            $this->tenantId(), $from, $from, $to, $to,
        ]));
    }

    /** Demand → order → receipt → bill, one row per demand form. */
    public function procurementTimeline(?string $from = null, ?string $to = null): Collection
    {
        return collect(DB::select('
            SELECT * FROM v_procurement_timeline
            WHERE tenant_id = ?
              AND (? IS NULL OR raised_at >= ?)
              AND (? IS NULL OR raised_at <= ?)
            ORDER BY raised_at
        ', [$this->tenantId(), $from, $from, $to, $to]));
    }
}
