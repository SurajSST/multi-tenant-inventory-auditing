<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The database-level controls, ported from the reference system's
 * prisma/integrity.sql to MariaDB.
 *
 * These rules live in the DATABASE, not the application. A bug in a service, a
 * future refactor, or somebody with a mysql prompt all hit the same wall. The
 * PHP layer checks first only so the user gets a readable message; the database
 * is the thing that guarantees it.
 *
 * Translation notes from the PostgreSQL original:
 *   RAISE EXCEPTION        -> SIGNAL SQLSTATE '45000' (MESSAGE_TEXT caps at 128 chars)
 *   BEFORE UPDATE OR DELETE-> two triggers; MariaDB allows one event each
 *   SELECT DISTINCT ON     -> ROW_NUMBER() in a derived table
 *   ORDER BY … NULLS LAST  -> ISNULL(col) first
 */
class IntegrityRules
{
    /** Applied in order. Every statement must succeed. */
    public static function createStatements(): array
    {
        return array_merge(
            self::separationOfDuties(),
            self::noSelfApproval(),
            self::noDoubleClaim(),
            self::actorMustBelong(),
            self::appendOnly(),
            self::moneyGuards(),
            self::readModels(),
        );
    }

    // ------------------------------------------------------------
    // 1. SEPARATION OF DUTIES
    //    The person who placed an order can never be the person who
    //    records that it arrived.
    // ------------------------------------------------------------
    private static function separationOfDuties(): array
    {
        return [
            'ALTER TABLE goods_receipts
               ADD CONSTRAINT chk_receipt_two_people
               CHECK (received_by_id <> ordered_by_id)',

            // ordered_by_id must genuinely mirror the order it points at, so the
            // column cannot be faked to slip past the CHECK above.
            "CREATE TRIGGER trg_receipt_orderer
               BEFORE INSERT ON goods_receipts
               FOR EACH ROW
             BEGIN
               DECLARE real_orderer CHAR(36) DEFAULT NULL;
               SELECT ordered_by_id INTO real_orderer
                 FROM purchase_orders WHERE id = NEW.purchase_order_id;
               IF real_orderer IS NULL THEN
                 SIGNAL SQLSTATE '45000'
                   SET MESSAGE_TEXT = 'That purchase order does not exist.';
               END IF;
               IF NEW.ordered_by_id <> real_orderer THEN
                 SIGNAL SQLSTATE '45000'
                   SET MESSAGE_TEXT = 'ordered_by_id does not match the purchase order.';
               END IF;
               IF NEW.received_by_id = real_orderer THEN
                 SIGNAL SQLSTATE '45000'
                   SET MESSAGE_TEXT = 'Separation of duties: whoever placed this order cannot receive it.';
               END IF;
             END",

            "CREATE TRIGGER trg_receipt_orderer_upd
               BEFORE UPDATE ON goods_receipts
               FOR EACH ROW
             BEGIN
               DECLARE real_orderer CHAR(36) DEFAULT NULL;
               SELECT ordered_by_id INTO real_orderer
                 FROM purchase_orders WHERE id = NEW.purchase_order_id;
               IF real_orderer IS NULL OR NEW.ordered_by_id <> real_orderer
                  OR NEW.received_by_id = real_orderer THEN
                 SIGNAL SQLSTATE '45000'
                   SET MESSAGE_TEXT = 'Separation of duties: whoever placed this order cannot receive it.';
               END IF;
             END",

            // A quantity received can never exceed the quantity ordered.
            'ALTER TABLE goods_receipt_lines
               ADD CONSTRAINT chk_receipt_qty
               CHECK (qty_received >= 0 AND qty_received <= qty_ordered)',
        ];
    }

    // ------------------------------------------------------------
    // 2. NOBODY SIGNS OFF THEIR OWN REQUEST
    // ------------------------------------------------------------
    private static function noSelfApproval(): array
    {
        return [
            "CREATE TRIGGER trg_no_self_approval
               BEFORE INSERT ON demand_approvals
               FOR EACH ROW
             BEGIN
               DECLARE requester CHAR(36) DEFAULT NULL;
               SELECT raised_by_id INTO requester FROM demand_forms WHERE id = NEW.demand_id;
               IF requester = NEW.actor_id THEN
                 SIGNAL SQLSTATE '45000'
                   SET MESSAGE_TEXT = 'A person cannot decide on a demand form they raised.';
               END IF;
             END",

            // A rejection must carry a reason.
            "ALTER TABLE demand_approvals
               ADD CONSTRAINT chk_reject_needs_reason
               CHECK (action <> 'REJECT' OR (reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason)) >= 5))",
        ];
    }

    // ------------------------------------------------------------
    // 2b. NO DOUBLE CLAIM
    //     One bill, one payment. A bill claimed out of petty cash cannot
    //     also be entered in the main register, and the reverse is equally
    //     refused — which it was not before this was found.
    //
    //     The single-table halves of this rule are structural: bills.bill_no
    //     is unique, and uniq_live_token_per_bill allows one live token per
    //     bill number. These two triggers close the cross-table direction.
    // ------------------------------------------------------------
    private static function noDoubleClaim(): array
    {
        return [
            "CREATE TRIGGER trg_bill_not_already_tokenised
               BEFORE INSERT ON bills
               FOR EACH ROW
             BEGIN
               IF EXISTS (
                 SELECT 1 FROM petty_cash_tokens
                  WHERE tenant_id = NEW.tenant_id
                    AND bill_no = NEW.bill_no
                    AND status <> 'VOIDED'
               ) THEN
                 SIGNAL SQLSTATE '45000'
                   SET MESSAGE_TEXT = 'That bill was already claimed from petty cash. It cannot also be entered here.';
               END IF;
             END",

            "CREATE TRIGGER trg_token_not_already_billed
               BEFORE INSERT ON petty_cash_tokens
               FOR EACH ROW
             BEGIN
               IF EXISTS (
                 SELECT 1 FROM bills
                  WHERE tenant_id = NEW.tenant_id AND bill_no = NEW.bill_no
               ) THEN
                 SIGNAL SQLSTATE '45000'
                   SET MESSAGE_TEXT = 'That bill is already in the main register. It cannot also be claimed from petty cash.';
               END IF;
             END",
        ];
    }

    // ------------------------------------------------------------
    // 2c. THE ACTOR HAS TO WORK THERE
    //     Every other parent-child link in this schema is guarded by a
    //     composite foreign key on (tenant_id, parent_id). The actor columns
    //     cannot be: they point at users, who are deliberately global, because
    //     one person may hold a posting at several schools.
    //
    //     So the same guarantee is made the way this system makes its other
    //     ones — with a trigger. Whoever raised, approved, ordered, received,
    //     billed, tokenised or counted must hold a live posting at the school
    //     the row belongs to.
    //
    //     The platform owner is exempt only because they are refused these
    //     actions in the first place; the exemption is what lets them exist
    //     without holding a posting anywhere.
    // ------------------------------------------------------------
    private static function actorMustBelong(): array
    {
        $out = [];

        foreach ([
            'demand_forms' => ['raised_by_id', 'Whoever raises a demand form must be posted to that school.'],
            'demand_approvals' => ['actor_id', 'Whoever decides on a demand form must be posted to that school.'],
            'purchase_orders' => ['ordered_by_id', 'Whoever places an order must be posted to that school.'],
            'goods_receipts' => ['received_by_id', 'Whoever verifies a delivery must be posted to that school.'],
            'bills' => ['entered_by_id', 'Whoever enters a bill must be posted to that school.'],
            'petty_cash_tokens' => ['issued_by_id', 'Whoever issues a token must be posted to that school.'],
            'stock_count_entries' => ['counted_by_id', 'Whoever enters a count must be posted to that school.'],
        ] as $table => [$column, $message]) {
            $out[] = "CREATE TRIGGER trg_actor_posted_{$table}
               BEFORE INSERT ON {$table}
               FOR EACH ROW
             BEGIN
               IF NOT EXISTS (
                 SELECT 1 FROM tenant_users tu
                  WHERE tu.tenant_id = NEW.tenant_id
                    AND tu.user_id = NEW.{$column}
                    AND tu.is_active = 1
               ) AND NOT EXISTS (
                 SELECT 1 FROM users u
                  WHERE u.id = NEW.{$column} AND u.is_platform_owner = 1
               ) THEN
                 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
               END IF;
             END";
        }

        return $out;
    }

    // ------------------------------------------------------------
    // 3. APPEND-ONLY TABLES
    //    The audit trail, the approval record and the stock count ledger
    //    can be added to and read. Nothing more.
    // ------------------------------------------------------------
    private static function appendOnly(): array
    {
        $out = [];

        foreach ([
            'audit_log' => 'The audit trail is append-only. History cannot be changed or deleted.',
            'stock_count_entries' => 'The stock ledger is append-only. Record a correcting count instead.',
            'demand_approvals' => 'Approvals are append-only. A decision cannot be changed or deleted.',
        ] as $table => $message) {
            foreach (['UPDATE', 'DELETE'] as $event) {
                $name = 'trg_append_only_'.$table.'_'.strtolower($event);
                $out[] = "CREATE TRIGGER {$name}
                   BEFORE {$event} ON {$table}
                   FOR EACH ROW
                 BEGIN
                   SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';
                 END";
            }
        }

        return $out;
    }

    // ------------------------------------------------------------
    // 4. MONEY GUARDS
    // ------------------------------------------------------------
    private static function moneyGuards(): array
    {
        return [
            'ALTER TABLE demand_lines
               ADD CONSTRAINT chk_line_positive
               CHECK (quantity > 0 AND unit_rate >= 0 AND line_total >= 0)',

            'ALTER TABLE demand_forms
               ADD CONSTRAINT chk_demand_floor
               CHECK (total_amount >= 0)',

            'ALTER TABLE purchase_orders
               ADD CONSTRAINT chk_order_positive
               CHECK (order_amount > 0)',

            'ALTER TABLE bills
               ADD CONSTRAINT chk_bill_positive
               CHECK (bill_amount > 0 AND vat_amount >= 0)',

            // A cleared variance must carry a written reason and a named clearer.
            "ALTER TABLE bills
               ADD CONSTRAINT chk_variance_note
               CHECK (
                 match_status <> 'VARIANCE_CLEARED'
                 OR (cleared_by_id IS NOT NULL
                     AND variance_note IS NOT NULL
                     AND CHAR_LENGTH(TRIM(variance_note)) >= 10)
               )",

            // A token is only valid once the bill has been sighted, and never
            // above the ceiling that was in force at the time.
            'ALTER TABLE petty_cash_tokens
               ADD CONSTRAINT chk_token_valid
               CHECK (amount > 0 AND amount <= ceiling_at_issue AND bill_sighted = 1)',

            // Approval bands must not overlap or leave gaps.
            'ALTER TABLE approval_tiers
               ADD CONSTRAINT chk_tier_range
               CHECK (max_amount IS NULL OR max_amount > min_amount)',
        ];
    }

    // ------------------------------------------------------------
    // 5. READ MODELS
    //    Current stock is derived, never stored, so the displayed figure
    //    and the history underneath it can never disagree.
    // ------------------------------------------------------------
    private static function readModels(): array
    {
        return [
            'CREATE OR REPLACE VIEW v_current_stock AS
             SELECT x.tenant_id,
                    x.item_type_id,
                    x.location_id,
                    x.quantity,
                    x.counted_at    AS last_counted_at,
                    x.counted_by_id AS last_counted_by,
                    x.source        AS last_source
             FROM (
               SELECT e.*,
                      ROW_NUMBER() OVER (
                        PARTITION BY e.item_type_id, e.location_id
                        ORDER BY e.counted_at DESC, e.id DESC
                      ) AS rn
               FROM stock_count_entries e
             ) x
             WHERE x.rn = 1',

            // The CROSS JOIN below is what makes the register read the way the
            // school reads it on paper: every item against every block, empty
            // cells included. Across tenants it would cross one school's items
            // with another school's blocks, which is why the tenant predicate
            // in the WHERE clause is load-bearing rather than cosmetic.
            'CREATE OR REPLACE VIEW v_stock_register AS
             SELECT it.tenant_id,
                    it.id            AS item_type_id,
                    it.name          AS item_name,
                    it.code_prefix,
                    it.lifespan,
                    c.name           AS category,
                    s.name           AS subcategory,
                    l.id             AS location_id,
                    l.name           AS block,
                    l.code           AS block_code,
                    COALESCE(cs.quantity, 0) AS quantity,
                    cs.last_counted_at,
                    u.full_name      AS last_counted_by
             FROM item_types it
             CROSS JOIN locations l
             JOIN categories c         ON c.id = it.category_id
             LEFT JOIN subcategories s ON s.id = it.subcategory_id
             LEFT JOIN v_current_stock cs ON cs.item_type_id = it.id AND cs.location_id = l.id
             LEFT JOIN users u         ON u.id = cs.last_counted_by
             WHERE it.is_active = 1 AND l.is_active = 1
               AND l.tenant_id = it.tenant_id',

            // Three-way match, computed live from the source rows. A bill is
            // MATCHED only when it equals the order and does not exceed approval.
            'CREATE OR REPLACE VIEW v_three_way_match AS
             SELECT b.tenant_id,
                    b.id            AS bill_id,
                    b.bill_no,
                    b.bill_date,
                    v.name          AS vendor,
                    d.ref           AS demand_ref,
                    po.ref          AS po_ref,
                    d.total_amount  AS approved_amount,
                    po.order_amount AS ordered_amount,
                    b.bill_amount   AS billed_amount,
                    (b.bill_amount - po.order_amount) AS variance_vs_order,
                    (b.bill_amount - d.total_amount)  AS variance_vs_approval,
                    b.match_status,
                    b.variance_note,
                    ub.full_name    AS entered_by,
                    uc.full_name    AS cleared_by
             FROM bills b
             JOIN vendors v               ON v.id = b.vendor_id
             LEFT JOIN purchase_orders po ON po.id = b.purchase_order_id
             LEFT JOIN demand_forms d     ON d.id = po.demand_id
             LEFT JOIN users ub           ON ub.id = b.entered_by_id
             LEFT JOIN users uc           ON uc.id = b.cleared_by_id',

            // Who touched what, in one place, for the management dashboard.
            'CREATE OR REPLACE VIEW v_procurement_timeline AS
             SELECT d.tenant_id,
                    d.id                AS demand_id,
                    d.ref               AS demand_ref,
                    d.total_amount,
                    d.status,
                    ur.full_name        AS raised_by,
                    d.created_at        AS raised_at,
                    po.ref              AS po_ref,
                    uo.full_name        AS ordered_by,
                    po.order_amount,
                    po.ordered_at,
                    urc.full_name       AS received_by,
                    gr.received_at,
                    gr.discrepancy_note,
                    b.bill_no,
                    b.bill_amount,
                    b.match_status
             FROM demand_forms d
             JOIN users ur                ON ur.id = d.raised_by_id
             LEFT JOIN purchase_orders po ON po.demand_id = d.id
             LEFT JOIN users uo           ON uo.id = po.ordered_by_id
             LEFT JOIN goods_receipts gr  ON gr.purchase_order_id = po.id
             LEFT JOIN users urc          ON urc.id = gr.received_by_id
             LEFT JOIN bills b            ON b.purchase_order_id = po.id',
        ];
    }

    // ------------------------------------------------------------
    // Applying, dropping and checking
    // ------------------------------------------------------------

    /** Idempotent: drops anything already there, then rebuilds it. */
    public static function apply(): void
    {
        self::drop();

        foreach (self::createStatements() as $sql) {
            DB::unprepared($sql);
        }
    }

    public static function drop(): void
    {
        foreach (self::views() as $view) {
            DB::unprepared("DROP VIEW IF EXISTS {$view}");
        }

        foreach (self::triggers() as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        foreach (self::constraints() as $constraint => $table) {
            // MariaDB has no ADD CONSTRAINT IF NOT EXISTS, so a stale constraint
            // is dropped first. Nothing to drop is not an error.
            try {
                DB::unprepared("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
            } catch (Throwable) {
                // Not there. Fine.
            }
        }
    }

    /** constraint name => table it sits on */
    public static function constraints(): array
    {
        return [
            'chk_receipt_two_people' => 'goods_receipts',
            'chk_receipt_qty' => 'goods_receipt_lines',
            'chk_reject_needs_reason' => 'demand_approvals',
            'chk_line_positive' => 'demand_lines',
            'chk_demand_floor' => 'demand_forms',
            'chk_order_positive' => 'purchase_orders',
            'chk_bill_positive' => 'bills',
            'chk_variance_note' => 'bills',
            'chk_token_valid' => 'petty_cash_tokens',
            'chk_tier_range' => 'approval_tiers',
        ];
    }

    public static function triggers(): array
    {
        return [
            'trg_receipt_orderer',
            'trg_receipt_orderer_upd',
            'trg_no_self_approval',
            'trg_bill_not_already_tokenised',
            'trg_token_not_already_billed',
            'trg_actor_posted_demand_forms',
            'trg_actor_posted_demand_approvals',
            'trg_actor_posted_purchase_orders',
            'trg_actor_posted_goods_receipts',
            'trg_actor_posted_bills',
            'trg_actor_posted_petty_cash_tokens',
            'trg_actor_posted_stock_count_entries',
            'trg_append_only_audit_log_update',
            'trg_append_only_audit_log_delete',
            'trg_append_only_stock_count_entries_update',
            'trg_append_only_stock_count_entries_delete',
            'trg_append_only_demand_approvals_update',
            'trg_append_only_demand_approvals_delete',
        ];
    }

    public static function views(): array
    {
        return [
            'v_procurement_timeline',
            'v_three_way_match',
            'v_stock_register',
            'v_current_stock',
        ];
    }

    /**
     * What is actually present in the database right now.
     *
     * @return array{constraints: array<string,bool>, triggers: array<string,bool>, views: array<string,bool>}
     */
    public static function status(): array
    {
        $schema = DB::getDatabaseName();

        $liveConstraints = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $schema)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->pluck('CONSTRAINT_NAME')
            ->all();

        $liveTriggers = DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', $schema)
            ->pluck('TRIGGER_NAME')
            ->all();

        $liveViews = DB::table('information_schema.VIEWS')
            ->where('TABLE_SCHEMA', $schema)
            ->pluck('TABLE_NAME')
            ->all();

        $check = fn (array $expected, array $live) => collect($expected)
            ->mapWithKeys(fn ($name) => [$name => in_array($name, $live, true)])
            ->all();

        return [
            'constraints' => $check(array_keys(self::constraints()), $liveConstraints),
            'triggers' => $check(self::triggers(), $liveTriggers),
            'views' => $check(self::views(), $liveViews),
        ];
    }
}
