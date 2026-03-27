<?php

namespace App\Services;

use App\Models\OrderAdjustmentLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\Auth;

class OrderAdjustmentService
{
    /**
     * Adjust a PO line's quantity, logging the change.
     */
    public static function adjustQuantity(
        PurchaseOrderLine $line,
        float $newQuantity,
        ?string $reason = null
    ): void {
        $oldQuantity = floatval($line->quantity);

        // Preserve original quantity on first adjustment
        if ($line->original_quantity === null) {
            $line->original_quantity = $oldQuantity;
        }

        // Log the adjustment
        OrderAdjustmentLog::create([
            'adjustable_type' => PurchaseOrderLine::class,
            'adjustable_id'   => $line->id,
            'field'           => 'quantity',
            'old_value'       => (string) $oldQuantity,
            'new_value'       => (string) $newQuantity,
            'reason'          => $reason,
            'adjusted_by'     => Auth::id(),
            'created_at'      => now(),
        ]);

        $line->update([
            'quantity'          => $newQuantity,
            'total_cost'        => round($newQuantity * floatval($line->unit_cost), 4),
            'adjusted_by'       => Auth::id(),
            'adjustment_reason' => $reason,
        ]);
    }

    /**
     * Adjust a PO line's unit cost, logging the change.
     */
    public static function adjustUnitCost(
        PurchaseOrderLine $line,
        float $newUnitCost,
        ?string $reason = null
    ): void {
        $oldCost = floatval($line->unit_cost);

        OrderAdjustmentLog::create([
            'adjustable_type' => PurchaseOrderLine::class,
            'adjustable_id'   => $line->id,
            'field'           => 'unit_cost',
            'old_value'       => (string) $oldCost,
            'new_value'       => (string) $newUnitCost,
            'reason'          => $reason,
            'adjusted_by'     => Auth::id(),
            'created_at'      => now(),
        ]);

        $line->update([
            'unit_cost'  => $newUnitCost,
            'total_cost' => round(floatval($line->quantity) * $newUnitCost, 4),
        ]);
    }

    /**
     * Recalculate PO totals after line adjustments.
     */
    public static function recalculatePoTotals(PurchaseOrder $po): void
    {
        $po->loadMissing('lines');

        $subtotal = $po->lines->sum(fn ($l) => floatval($l->total_cost));
        $taxAmount = round($subtotal * (floatval($po->tax_percent) / 100), 4);
        $deliveryCharges = floatval($po->delivery_charges);

        $po->update([
            'subtotal'     => round($subtotal, 4),
            'tax_amount'   => $taxAmount,
            'total_amount' => round($subtotal + $taxAmount + $deliveryCharges, 4),
        ]);
    }

    /**
     * Get adjustment history for a PO line.
     */
    public static function getHistory(PurchaseOrderLine $line): \Illuminate\Database\Eloquent\Collection
    {
        return $line->adjustmentLogs()
            ->with('adjustedBy')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get the next delivery sequence number for a PO.
     */
    public static function nextDeliverySequence(int $purchaseOrderId): int
    {
        $max = \App\Models\DeliveryOrder::where('purchase_order_id', $purchaseOrderId)
            ->max('delivery_sequence');

        return ($max ?? 0) + 1;
    }
}
