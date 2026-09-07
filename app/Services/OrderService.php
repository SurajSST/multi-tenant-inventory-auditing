<?php

namespace App\Services;

use App\Enums\DemandStatus;
use App\Enums\Lifespan;
use App\Enums\OrderStatus;
use App\Enums\ReceiptCondition;
use App\Enums\UnitStatus;
use App\Models\AssetUnit;
use App\Models\Category;
use App\Models\DemandForm;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Money;
use App\Support\RefCounter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private AuditLogger $audit,
        private InventoryService $inventory,
        private AccountingService $accounting,
        private SettingService $settings,
        private Notifier $notify,
    ) {}

    /** Approved demands that still have un-ordered items. */
    public function awaitingOrder(): Collection
    {
        return DemandForm::query()
            ->where('status', DemandStatus::APPROVED)
            ->with([
                'lines.itemType',
                'lines.poLines.order',
                'raisedBy', 'raisedBy.currentMembership',
                'approvals.actor', 'approvals.actor.currentMembership',
            ])
            ->orderBy('closed_at')
            ->get()
            ->filter(fn (DemandForm $d) => ! $d->isFullyOrdered())
            ->values();
    }

    /** Total count of approved demands that still have un-ordered items, calculated directly in SQL. */
    public function awaitingOrderCount(): int
    {
        return DemandForm::query()
            ->where('status', DemandStatus::APPROVED)
            ->whereHas('lines', function ($q) {
                $q->whereRaw('quantity > (
                    SELECT COALESCE(SUM(pol.quantity_ordered), 0)
                    FROM purchase_order_lines pol
                    JOIN purchase_orders po ON po.id = pol.purchase_order_id
                    WHERE pol.demand_line_id = demand_lines.id
                      AND po.status != ?
                )', [OrderStatus::CANCELLED->value]);
            })
            ->count();
    }

    /**
     * Create a purchase order against an approved demand. Supports multi-PO splits.
     *
     * @param  array{
     *     demand_id: string,
     *     vendor_id?: string|null,
     *     vendor_name?: string|null,
     *     vendor_pan_vat?: string|null,
     *     order_amount?: string|float|null,
     *     expected_date?: string|null,
     *     note?: string|null,
     *     lines?: array<int, array{demand_line_id: string, quantity_ordered: int, unit_price?: string|float|null, discount?: string|float|null, tax?: string|float|null}>
     * }  $data
     */
    public function create(array $data, User $user): PurchaseOrder
    {
        if (empty($data['vendor_id']) && empty($data['vendor_name'])) {
            throw ValidationException::withMessages(['vendor_name' => 'Name the vendor.']);
        }

        return DB::transaction(function () use ($data, $user) {
            $demand = DemandForm::with('lines.poLines.order')->lockForUpdate()->findOrFail($data['demand_id']);

            if ($demand->status !== DemandStatus::APPROVED) {
                throw ValidationException::withMessages([
                    'demand_id' => 'Only a fully approved demand can be turned into an order.',
                ]);
            }

            if ($demand->isFullyOrdered()) {
                $existingOrderRefs = $demand->purchaseOrders()->pluck('ref')->filter()->join(', ');
                $refSuffix = $existingOrderRefs ? " ({$existingOrderRefs})" : '';

                throw ValidationException::withMessages([
                    'demand_id' => "Demand ({$demand->ref}) has already been fully ordered{$refSuffix}.",
                ]);
            }

            $demandLinesById = $demand->lines->keyBy('id');
            $linesToCreate = [];
            $computedOrderAmount = '0.00';

            if (! empty($data['lines'])) {
                foreach ($data['lines'] as $lineInput) {
                    $dLine = $demandLinesById->get($lineInput['demand_line_id']);
                    if (! $dLine) {
                        throw ValidationException::withMessages(['lines' => 'A selected item does not belong to this demand.']);
                    }

                    $qtyOrdered = (int) $lineInput['quantity_ordered'];
                    if ($qtyOrdered <= 0) {
                        continue;
                    }

                    $remainingAllowed = $dLine->remainingToOrderQty();
                    if ($qtyOrdered > $remainingAllowed) {
                        throw ValidationException::withMessages([
                            'lines' => sprintf(
                                '%s: only %d units remaining to order from approval (attempted to order %d).',
                                $dLine->item_name,
                                $remainingAllowed,
                                $qtyOrdered,
                            ),
                        ]);
                    }

                    $unitPrice = isset($lineInput['unit_price']) ? Money::of($lineInput['unit_price']) : Money::of($dLine->unit_rate);
                    $discount = isset($lineInput['discount']) ? Money::of($lineInput['discount']) : '0.00';
                    $tax = isset($lineInput['tax']) ? Money::of($lineInput['tax']) : '0.00';
                    $lineTotal = Money::add(Money::sub(Money::mul($unitPrice, $qtyOrdered), $discount), $tax);

                    $computedOrderAmount = Money::add($computedOrderAmount, $lineTotal);

                    $linesToCreate[] = [
                        'demand_line' => $dLine,
                        'quantity_ordered' => $qtyOrdered,
                        'unit_price' => $unitPrice,
                        'discount' => $discount,
                        'tax' => $tax,
                        'line_total' => $lineTotal,
                    ];
                }

                if (empty($linesToCreate)) {
                    throw ValidationException::withMessages(['lines' => 'At least one line item must have a quantity greater than zero.']);
                }
            } else {
                // Fallback for legacy / simple calls: order all remaining un-ordered items
                foreach ($demand->lines as $dLine) {
                    $remainingAllowed = $dLine->remainingToOrderQty();
                    if ($remainingAllowed <= 0) {
                        continue;
                    }

                    $unitPrice = Money::of($dLine->unit_rate);
                    $lineTotal = Money::mul($unitPrice, $remainingAllowed);
                    $computedOrderAmount = Money::add($computedOrderAmount, $lineTotal);

                    $linesToCreate[] = [
                        'demand_line' => $dLine,
                        'quantity_ordered' => $remainingAllowed,
                        'unit_price' => $unitPrice,
                        'discount' => '0.00',
                        'tax' => '0.00',
                        'line_total' => $lineTotal,
                    ];
                }
            }

            $orderAmount = ! empty($data['order_amount']) ? Money::of($data['order_amount']) : $computedOrderAmount;

            $overBy = Money::sub($orderAmount, $demand->total_amount);

            if (Money::gt($overBy, 0) && ! $this->settings->allowOrderAboveApproval()) {
                throw ValidationException::withMessages([
                    'order_amount' => sprintf(
                        'Order amount (%s) exceeds approved total (%s) and ordering above approval is disabled in settings.',
                        Money::npr($orderAmount),
                        Money::npr($demand->total_amount)
                    ),
                ]);
            }

            $vendorId = $data['vendor_id'] ?? null;

            if (! $vendorId) {
                $vendor = Vendor::firstOrCreate(
                    ['name' => trim($data['vendor_name'])],
                    ['pan_vat' => $data['vendor_pan_vat'] ?? null],
                );

                if (! empty($data['vendor_pan_vat']) && ! $vendor->pan_vat) {
                    $vendor->update(['pan_vat' => $data['vendor_pan_vat']]);
                }

                $vendorId = $vendor->id;
            }

            ['ref' => $ref, 'fiscal_year' => $fiscalYear] = RefCounter::next('PO');

            $order = PurchaseOrder::create([
                'ref' => $ref,
                'fiscal_year' => $fiscalYear,
                'demand_id' => $demand->id,
                'vendor_id' => $vendorId,
                'order_amount' => $orderAmount,
                'status' => OrderStatus::PLACED,
                'expected_date' => $data['expected_date'] ?? null,
                'note' => $data['note'] ?? null,
                'ordered_by_id' => $user->id,
            ]);

            foreach ($linesToCreate as $item) {
                /** @var DemandLine $dLine */
                $dLine = $item['demand_line'];

                $order->lines()->create([
                    'demand_line_id' => $dLine->id,
                    'item_type_id' => $dLine->item_type_id,
                    'description' => $dLine->item_name,
                    'quantity_ordered' => $item['quantity_ordered'],
                    'unit' => 'piece',
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'tax' => $item['tax'],
                    'line_total' => $item['line_total'],
                ]);
            }

            $order->load(['vendor', 'lines']);

            $this->audit->record(
                action: 'ORDER_PLACED',
                entity: 'purchase_orders',
                entityId: $order->id,
                detail: sprintf(
                    '%s placed with %s for %s against %s by %s%s',
                    $ref,
                    $order->vendor->name,
                    Money::npr($orderAmount),
                    $demand->ref,
                    $user->full_name,
                    Money::gt($overBy, 0) ? ' — '.Money::npr($overBy).' ABOVE the approved amount' : '',
                ),
                actor: $user,
                after: [
                    'ref' => $ref,
                    'order_amount' => $orderAmount,
                    'approved_amount' => (string) $demand->total_amount,
                ],
            );

            return $order;
        });
    }

    public function list(?OrderStatus $status = null, bool $pendingReceiptOnly = false, ?string $search = null, int $perPage = 25): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($pendingReceiptOnly, fn ($q) => $q->whereIn('status', [OrderStatus::PLACED, OrderStatus::PART_RECEIVED]))
            ->when($search, function ($q, $search) {
                $search = trim($search);
                $q->where(function ($sub) use ($search) {
                    $sub->where('ref', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('demand', fn ($d) => $d->where('ref', 'like', "%{$search}%"))
                        ->orWhereHas('orderedBy', fn ($u) => $u->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('lines', fn ($l) => $l->where('description', 'like', "%{$search}%"));
                });
            })
            ->with([
                'vendor:id,name',
                'orderedBy:id,full_name', 'orderedBy.currentMembership',
                'demand:id,ref',
                'receipt',
                'receipt.receivedBy:id,full_name', 'receipt.receivedBy.currentMembership',
            ])
            ->orderByDesc('ordered_at')
            ->paginate($perPage);
    }

    /**
     * Orders still waiting on somebody to verify them, excluding the ones this
     * person placed — they are barred from receiving those, so showing them
     * would only be an invitation to try.
     */
    public function awaitingReceipt(User $except, int $limit = 10): Collection
    {
        return PurchaseOrder::query()
            ->whereIn('status', [OrderStatus::PLACED, OrderStatus::PART_RECEIVED])
            ->where('ordered_by_id', '!=', $except->id)
            ->with(['vendor:id,name', 'demand:id,ref'])
            ->orderBy('ordered_at')
            ->limit($limit)
            ->get();
    }

    public function find(string $id): PurchaseOrder
    {
        return PurchaseOrder::with([
            'vendor', 'orderedBy', 'orderedBy.currentMembership',
            'demand.lines.itemType',
            'demand.raisedBy', 'demand.raisedBy.currentMembership',
            'demand.approvals.actor', 'demand.approvals.actor.currentMembership',
            'lines.demandLine',
            'receipts.receivedBy', 'receipts.receivedBy.currentMembership',
            'receipts.location', 'receipts.lines.demandLine', 'receipts.lines.poLine',
            'bills.enteredBy', 'bills.enteredBy.currentMembership',
            'bills.clearedBy', 'bills.clearedBy.currentMembership',
            'bills.lines.poLine',
            'bills.allocations.payment',
        ])->findOrFail($id);
    }

    /**
     * Cancel an order if no goods have been received yet.
     */
    public function cancel(string $orderId, User $user, string $reason): PurchaseOrder
    {
        $order = PurchaseOrder::with('receipts')->findOrFail($orderId);

        if ($order->status === OrderStatus::CANCELLED) {
            throw ValidationException::withMessages(['order' => 'This purchase order is already cancelled.']);
        }

        if ($order->receipts->count() > 0) {
            throw ValidationException::withMessages([
                'order' => 'Cannot cancel an order that has goods receipt records. Discrepancies must be processed via returns or adjustments.',
            ]);
        }

        $order->update(['status' => OrderStatus::CANCELLED]);

        $this->audit->record(
            action: 'ORDER_CANCELLED',
            entity: 'purchase_orders',
            entityId: $order->id,
            detail: sprintf('Purchase order %s cancelled by %s. Reason: %s', $order->ref, $user->full_name, $reason),
            actor: $user,
            after: ['status' => OrderStatus::CANCELLED->value, 'reason' => $reason]
        );

        return $order;
    }

    /**
     * Verifying receipt. Supports partial receiving, multiple shipments, and PO lines.
     *
     * @param  array<int, array{purchase_order_line_id?: string|null, demand_line_id?: string|null, qty_received: int, remark?: string|null}>  $lines
     * @return array{receipt: GoodsReceipt, units_posted: int, partial: bool}
     */
    public function receive(string $orderId, array $lines, array $meta, User $user): array
    {
        $order = PurchaseOrder::with(['receipts.lines', 'lines.demandLine', 'orderedBy', 'vendor'])
            ->findOrFail($orderId);

        if ($order->status === OrderStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'order' => 'This order was cancelled and cannot receive goods.',
            ]);
        }

        if ($order->status === OrderStatus::RECEIVED) {
            throw ValidationException::withMessages([
                'order' => 'This order has already been completely received.',
            ]);
        }

        if ($order->ordered_by_id === $user->id) {
            throw new AuthorizationException(sprintf(
                'You placed %s. Somebody else must verify that it arrived — that separation is the whole point of the control.',
                $order->ref,
            ));
        }

        if (! Location::whereKey($meta['location_id'] ?? null)->exists()) {
            throw ValidationException::withMessages([
                'location_id' => 'Name the block the goods were put into.',
            ]);
        }

        $poLinesById = $order->lines->keyBy('id');
        $poLinesByDemandLineId = $order->lines->keyBy('demand_line_id');

        // Check cumulative quantities already received across all receipts for this order
        $priorTotals = GoodsReceiptLine::whereIn('purchase_order_line_id', $poLinesById->keys())
            ->groupBy('purchase_order_line_id')
            ->selectRaw('purchase_order_line_id, sum(qty_received) as total_received')
            ->pluck('total_received', 'purchase_order_line_id');

        $totalReceivingNow = 0;
        $matchedLines = [];

        foreach ($lines as $line) {
            $poLine = null;
            if (! empty($line['purchase_order_line_id'])) {
                $poLine = $poLinesById->get($line['purchase_order_line_id']);
            } elseif (! empty($line['demand_line_id'])) {
                $poLine = $poLinesByDemandLineId->get($line['demand_line_id']);
            }

            if (! $poLine) {
                throw ValidationException::withMessages([
                    'lines' => 'A receipt line does not belong to this order.',
                ]);
            }

            $qty = (int) $line['qty_received'];
            $totalReceivingNow += $qty;

            $alreadyReceived = (int) ($priorTotals->get($poLine->id) ?? 0);
            $remainingAllowed = max(0, $poLine->quantity_ordered - $alreadyReceived);

            if ($qty < 0 || $qty > $remainingAllowed) {
                throw ValidationException::withMessages([
                    'lines' => sprintf(
                        '%s: %d ordered, %d previously received, %d remaining. You entered %d.',
                        $poLine->description,
                        $poLine->quantity_ordered,
                        $alreadyReceived,
                        $remainingAllowed,
                        $qty,
                    ),
                ]);
            }

            $matchedLines[] = [
                'po_line' => $poLine,
                'qty_received' => $qty,
                'remark' => $line['remark'] ?? null,
            ];
        }

        if ($totalReceivingNow <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'At least one item must have a received quantity greater than zero.',
            ]);
        }

        $result = DB::transaction(function () use ($order, $matchedLines, $meta, $user, $priorTotals) {
            $receipt = GoodsReceipt::create([
                'purchase_order_id' => $order->id,
                'ordered_by_id' => $order->ordered_by_id,
                'received_by_id' => $user->id,
                'location_id' => $meta['location_id'],
                'condition' => $meta['condition'] ?? ReceiptCondition::GOOD,
                'discrepancy_note' => $meta['discrepancy_note'] ?? null,
                'challan_no' => $meta['challan_no'] ?? null,
                'attachment_path' => $meta['attachment_path'] ?? null,
            ]);

            $posted = 0;

            foreach ($matchedLines as $item) {
                /** @var PurchaseOrderLine $poLine */
                $poLine = $item['po_line'];
                $qtyReceived = $item['qty_received'];
                $demandLine = $poLine->demandLine;

                $grLine = $receipt->lines()->create([
                    'purchase_order_line_id' => $poLine->id,
                    'demand_line_id' => $poLine->demand_line_id,
                    'location_id' => $meta['location_id'],
                    'qty_ordered' => $poLine->quantity_ordered,
                    'qty_received' => $qtyReceived,
                    'remark' => $item['remark'],
                ]);

                if ($qtyReceived > 0) {
                    $itemTypeId = $poLine->item_type_id ?? $demandLine?->item_type_id;

                    // Auto-register custom item if not linked to catalog so stock is never lost
                    if (! $itemTypeId) {
                        $itemName = $poLine->description;
                        $itemType = ItemType::where('name', $itemName)->first();

                        if (! $itemType) {
                            $category = Category::where('is_active', true)->first()
                                ?? Category::firstOrCreate(
                                    ['code' => 'GEN'],
                                    ['name' => 'General Supplies', 'sort_order' => 99, 'is_active' => true]
                                );

                            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $itemName), 0, 4)) ?: 'ITEM';

                            $itemType = ItemType::create([
                                'name' => $itemName,
                                'code_prefix' => $prefix,
                                'category_id' => $category->id,
                                'lifespan' => Lifespan::CONSUMABLE,
                                'unit_of_measure' => 'piece',
                                'indicative_rate' => $poLine->unit_price,
                                'reorder_level' => 5,
                                'is_active' => true,
                            ]);
                        }

                        $itemTypeId = $itemType->id;
                        $poLine->update(['item_type_id' => $itemTypeId]);
                        $demandLine?->update(['item_type_id' => $itemTypeId]);
                    } else {
                        $itemType = ItemType::find($itemTypeId);
                    }

                    $this->inventory->postReceipt(
                        itemTypeId: $itemTypeId,
                        locationId: $meta['location_id'],
                        quantity: $qtyReceived,
                        receiver: $user,
                        receiptId: $receipt->id,
                        note: "Received against {$order->ref}",
                    );

                    // Track individual durables in AssetUnit (Prompt Section 17)
                    if ($itemType && $itemType->lifespan === Lifespan::DURABLE) {
                        for ($i = 0; $i < $qtyReceived; $i++) {
                            $nextNo = ((int) AssetUnit::where('item_type_id', $itemType->id)->max('unit_no')) + 1;
                            AssetUnit::create([
                                'item_type_id' => $itemType->id,
                                'purchase_order_line_id' => $poLine->id,
                                'goods_receipt_line_id' => $grLine->id,
                                'unit_no' => $nextNo,
                                'unit_code' => "{$itemType->code_prefix}.{$nextNo}",
                                'location_id' => $meta['location_id'],
                                'status' => UnitStatus::ACTIVE,
                                'acquired_on' => now()->toDateString(),
                                'purchase_cost' => $poLine->unit_price,
                                'note' => "Received on {$receipt->challan_no} against {$order->ref}",
                            ]);
                        }
                    }

                    $posted += $qtyReceived;
                }
            }

            // Check if order is fully received or partial
            $orderFullyReceived = true;
            foreach ($order->lines as $pLine) {
                $already = (int) ($priorTotals->get($pLine->id) ?? 0);
                $thisLineRec = 0;
                foreach ($matchedLines as $m) {
                    if ($m['po_line']->id === $pLine->id) {
                        $thisLineRec = $m['qty_received'];
                        break;
                    }
                }
                if (($already + $thisLineRec) < $pLine->quantity_ordered) {
                    $orderFullyReceived = false;
                    break;
                }
            }

            $newStatus = $orderFullyReceived ? OrderStatus::RECEIVED : OrderStatus::PART_RECEIVED;
            $order->update(['status' => $newStatus]);

            $receipt->load('location');

            // Record accounting accrual for goods received
            $this->accounting->recordGoodsReceiptAccrual($receipt);

            $this->audit->record(
                action: 'GOODS_RECEIVED',
                entity: 'goods_receipts',
                entityId: $receipt->id,
                detail: sprintf(
                    '%s from %s verified by %s (ordered by %s); %d units posted to %s%s',
                    $order->ref,
                    $order->vendor->name,
                    $user->full_name,
                    $order->orderedBy->full_name,
                    $posted,
                    $receipt->location->name,
                    ! empty($meta['discrepancy_note']) ? '; discrepancy: '.$meta['discrepancy_note'] : '',
                ),
                actor: $user,
                after: [
                    'units_posted' => $posted,
                    'condition' => ($meta['condition'] ?? ReceiptCondition::GOOD)->value,
                    'partial' => ! $orderFullyReceived,
                ],
            );

            return ['receipt' => $receipt, 'units_posted' => $posted, 'partial' => ! $orderFullyReceived];
        });

        $this->notify->goodsReceived($order, $result['receipt']);

        return $result;
    }
}
