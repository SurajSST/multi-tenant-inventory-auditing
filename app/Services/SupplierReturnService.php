<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Support\Money;
use App\Support\RefCounter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierReturnService
{
    public function __construct(
        private AuditLogger $audit,
        private InventoryService $inventory,
        private AccountingService $accounting,
    ) {}

    /**
     * Create and post a return to a supplier against verified goods receipts.
     *
     * @param  array{
     *     goods_receipt_id: string,
     *     reason: string,
     *     lines: array<int, array{goods_receipt_line_id: string, quantity: int, reason?: string|null}>
     * }  $data
     */
    public function create(array $data, User $user): SupplierReturn
    {
        if (empty($data['reason'])) {
            throw ValidationException::withMessages(['reason' => 'A reason for return is required.']);
        }

        if (empty($data['lines'])) {
            throw ValidationException::withMessages(['lines' => 'Select at least one item to return.']);
        }

        $receipt = GoodsReceipt::with(['purchaseOrder.vendor', 'lines.poLine', 'lines.demandLine', 'lines.location'])
            ->findOrFail($data['goods_receipt_id']);

        $receiptLinesById = $receipt->lines->keyBy('id');
        $linesToPost = [];
        $totalReturnAmount = '0.00';

        foreach ($data['lines'] as $lineInput) {
            $grLine = $receiptLinesById->get($lineInput['goods_receipt_line_id']);
            if (! $grLine) {
                throw ValidationException::withMessages(['lines' => 'A line does not belong to this goods receipt.']);
            }

            $qty = (int) $lineInput['quantity'];
            if ($qty <= 0) {
                throw ValidationException::withMessages(['lines' => 'Return quantity must be greater than zero.']);
            }

            // Cannot return more than was received on this line
            if ($qty > $grLine->qty_received) {
                throw ValidationException::withMessages([
                    'lines' => sprintf(
                        'Cannot return %d units: only %d units were received on this line.',
                        $qty,
                        $grLine->qty_received,
                    ),
                ]);
            }

            // Determine unit price from PO line or Demand line
            $unitPrice = $grLine->poLine?->unit_price
                ?? $grLine->demandLine?->unit_rate
                ?? '0.00';

            $lineTotal = Money::mul($unitPrice, $qty);
            $totalReturnAmount = Money::add($totalReturnAmount, $lineTotal);

            $linesToPost[] = [
                'receipt_line' => $grLine,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'reason' => $lineInput['reason'] ?? $data['reason'],
            ];
        }

        return DB::transaction(function () use ($receipt, $data, $linesToPost, $totalReturnAmount, $user) {
            ['ref' => $ref, 'fiscal_year' => $fiscalYear] = RefCounter::next('SR');

            $return = SupplierReturn::create([
                'ref' => $ref,
                'fiscal_year' => $fiscalYear,
                'vendor_id' => $receipt->purchaseOrder->vendor_id,
                'purchase_order_id' => $receipt->purchase_order_id,
                'goods_receipt_id' => $receipt->id,
                'total_amount' => $totalReturnAmount,
                'reason' => $data['reason'],
                'status' => 'POSTED',
                'returned_by_id' => $user->id,
            ]);

            foreach ($linesToPost as $item) {
                /** @var GoodsReceiptLine $grLine */
                $grLine = $item['receipt_line'];

                $itemTypeId = $grLine->poLine?->item_type_id ?? $grLine->demandLine?->item_type_id;

                $return->lines()->create([
                    'goods_receipt_line_id' => $grLine->id,
                    'purchase_order_line_id' => $grLine->purchase_order_line_id,
                    'item_type_id' => $itemTypeId,
                    'location_id' => $grLine->location_id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'reason' => $item['reason'],
                ]);

                // Post inventory ledger deduction
                if ($itemTypeId) {
                    $this->inventory->postReturn(
                        itemTypeId: $itemTypeId,
                        locationId: $grLine->location_id,
                        quantity: $item['quantity'],
                        actor: $user,
                        note: "Supplier return {$ref} for PO {$receipt->purchaseOrder->ref}: {$item['reason']}"
                    );
                }
            }

            // Post double-entry accounting entry
            $this->accounting->recordSupplierReturn($return, $user);

            $return->load('vendor');

            $this->audit->record(
                action: 'SUPPLIER_RETURN_POSTED',
                entity: 'supplier_returns',
                entityId: $return->id,
                detail: sprintf(
                    'Supplier return %s to %s for %s by %s. Reason: %s',
                    $ref,
                    $return->vendor->name,
                    Money::npr($totalReturnAmount),
                    $user->full_name,
                    $data['reason']
                ),
                actor: $user,
                after: [
                    'ref' => $ref,
                    'total_amount' => $totalReturnAmount,
                    'vendor' => $return->vendor->name,
                ]
            );

            return $return;
        });
    }

    public function list(int $perPage = 25): LengthAwarePaginator
    {
        return SupplierReturn::query()
            ->with(['vendor:id,name', 'returnedBy:id,full_name', 'purchaseOrder:id,ref', 'lines.itemType'])
            ->orderByDesc('returned_at')
            ->paginate($perPage);
    }
}
