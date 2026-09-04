<?php

namespace App\Services;

use App\Enums\DemandStatus;
use App\Enums\OrderStatus;
use App\Enums\ReceiptCondition;
use App\Models\DemandForm;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\PurchaseOrder;
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
        private Notifier $notify,
    ) {}

    /** Approved demands with no order against them yet. */
    public function awaitingOrder(): Collection
    {
        return DemandForm::query()
            ->where('status', DemandStatus::APPROVED)
            ->whereDoesntHave('orders')
            ->with(['lines.itemType', 'raisedBy', 'raisedBy.currentMembership', 'approvals.actor'])
            ->orderBy('closed_at')
            ->get();
    }

    /**
     * @param  array{demand_id: string, vendor_id?: string|null, vendor_name?: string|null, vendor_pan_vat?: string|null, order_amount: string|float, expected_date?: string|null, note?: string|null}  $data
     */
    public function create(array $data, User $user): PurchaseOrder
    {
        if (empty($data['vendor_id']) && empty($data['vendor_name'])) {
            throw ValidationException::withMessages(['vendor_name' => 'Name the vendor.']);
        }

        $orderAmount = Money::of($data['order_amount']);

        return DB::transaction(function () use ($data, $user, $orderAmount) {
            // Locked and re-read inside the transaction. Read outside it, two
            // clerks pressing Save at the same moment both saw "no order yet"
            // and both placed one against a single approval. uniq_order_per_demand
            // is the backstop; this is what gives the second one a readable answer.
            $demand = DemandForm::lockForUpdate()->findOrFail($data['demand_id']);

            if ($demand->status !== DemandStatus::APPROVED) {
                throw ValidationException::withMessages([
                    'demand_id' => 'Only a fully approved demand can be turned into an order.',
                ]);
            }

            $existing = PurchaseOrder::where('demand_id', $demand->id)->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'demand_id' => "An order ({$existing->ref}) already exists for this demand.",
                ]);
            }

            $overBy = Money::sub($orderAmount, $demand->total_amount);
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
                'expected_date' => $data['expected_date'] ?? null,
                'note' => $data['note'] ?? null,
                'ordered_by_id' => $user->id,
            ]);

            $order->load('vendor');

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

    public function list(?OrderStatus $status = null, bool $pendingReceiptOnly = false, int $perPage = 25): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($pendingReceiptOnly, fn ($q) => $q->whereDoesntHave('receipt'))
            // Only what the list actually prints. It used to pull every demand
            // line, every receipt line and every bill for orders it then showed
            // a single amount for.
            ->with([
                'vendor:id,name',
                'orderedBy:id,full_name', 'orderedBy.currentMembership',
                'demand:id,ref',
                'receipt:id,purchase_order_id,received_by_id,received_at',
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
            ->whereDoesntHave('receipt')
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
            'demand.lines.itemType', 'demand.raisedBy', 'demand.approvals.actor',
            'receipt.receivedBy', 'receipt.receivedBy.currentMembership',
            'receipt.location', 'receipt.lines.demandLine',
            'bills.enteredBy', 'bills.clearedBy',
        ])->findOrFail($id);
    }

    /**
     * Verifying receipt. Two separate gates stand in the way of one person
     * doing both halves of a transaction:
     *
     *   1. the check below, which gives a readable message,
     *   2. a CHECK constraint plus a trigger in MariaDB, which no role, no bug
     *      in this service and no direct mysql session can get around.
     *
     * Received quantities post straight into the stock ledger for the block
     * named by the receiver.
     *
     * @param  array<int, array{demand_line_id: string, qty_received: int, remark?: string|null}>  $lines
     * @return array{receipt: GoodsReceipt, units_posted: int, partial: bool}
     */
    public function receive(string $orderId, array $lines, array $meta, User $user): array
    {
        $order = PurchaseOrder::with(['receipt', 'demand.lines', 'orderedBy', 'vendor'])
            ->findOrFail($orderId);

        if ($order->receipt) {
            throw ValidationException::withMessages([
                'order' => 'This order has already been received.',
            ]);
        }

        if ($order->ordered_by_id === $user->id) {
            throw new AuthorizationException(sprintf(
                'You placed %s. Somebody else must verify that it arrived — that separation is the whole point of the control.',
                $order->ref,
            ));
        }

        $byLineId = $order->demand->lines->keyBy('id');

        foreach ($lines as $line) {
            $demandLine = $byLineId->get($line['demand_line_id']);

            if (! $demandLine) {
                throw ValidationException::withMessages([
                    'lines' => 'A receipt line does not belong to this order.',
                ]);
            }

            $qty = (int) $line['qty_received'];

            // chk_receipt_qty refuses this at the database, but a raw constraint
            // violation is an error page, not an answer. More arrived than was
            // ordered is a real thing worth asking about, so say so plainly.
            if ($qty < 0 || $qty > $demandLine->quantity) {
                throw ValidationException::withMessages([
                    'lines' => sprintf(
                        '%s: %d ordered, %d entered as received. A receipt cannot record more than was ordered — if more arrived, the order has to be corrected first.',
                        $demandLine->item_name,
                        $demandLine->quantity,
                        $qty,
                    ),
                ]);
            }
        }

        if (! Location::whereKey($meta['location_id'] ?? null)->exists()) {
            throw ValidationException::withMessages([
                'location_id' => 'Name the block the goods were put into.',
            ]);
        }

        $result = DB::transaction(function () use ($order, $lines, $meta, $user) {
            $receipt = GoodsReceipt::create([
                'purchase_order_id' => $order->id,
                // The trigger re-checks this against the order itself.
                'ordered_by_id' => $order->ordered_by_id,
                'received_by_id' => $user->id,
                'location_id' => $meta['location_id'],
                'condition' => $meta['condition'] ?? ReceiptCondition::GOOD,
                'discrepancy_note' => $meta['discrepancy_note'] ?? null,
                'challan_no' => $meta['challan_no'] ?? null,
                'attachment_path' => $meta['attachment_path'] ?? null,
            ]);

            $posted = 0;
            $short = false;

            foreach ($lines as $line) {
                $demandLine = $order->demand->lines->firstWhere('id', $line['demand_line_id']);
                $qtyReceived = (int) $line['qty_received'];

                $receipt->lines()->create([
                    'demand_line_id' => $demandLine->id,
                    'qty_ordered' => $demandLine->quantity,
                    'qty_received' => $qtyReceived,
                    'remark' => $line['remark'] ?? null,
                ]);

                if ($qtyReceived < $demandLine->quantity) {
                    $short = true;
                }

                // Only items already on the register can post into the ledger.
                // A brand-new item has to be added in Setup first.
                if ($demandLine->item_type_id && $qtyReceived > 0) {
                    $this->inventory->postReceipt(
                        itemTypeId: $demandLine->item_type_id,
                        locationId: $meta['location_id'],
                        quantity: $qtyReceived,
                        receiver: $user,
                        receiptId: $receipt->id,
                        note: "Received against {$order->ref}",
                    );

                    $posted += $qtyReceived;
                }
            }

            $order->update([
                'status' => $short ? OrderStatus::PART_RECEIVED : OrderStatus::RECEIVED,
            ]);

            $receipt->load('location');

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
                    'partial' => $short,
                ],
            );

            return ['receipt' => $receipt, 'units_posted' => $posted, 'partial' => $short];
        });

        // After the commit. The stock is on the ledger before the person who
        // ordered it is told that it is.
        $this->notify->goodsReceived($order, $result['receipt']);

        return $result;
    }
}
