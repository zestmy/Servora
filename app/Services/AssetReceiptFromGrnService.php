<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\GoodsReceivedNote;
use Illuminate\Support\Facades\Auth;

/**
 * Receiving an ordered asset into the register.
 *
 * An asset on a delivery is received exactly like one keyed in by hand under
 * Assets ▸ Receipts — the same AssetMovement, so AssetOnHandService keeps
 * being the only thing that works out what an outlet holds. What changes is
 * who types it: nobody. The delivery already said what arrived.
 *
 * IT DOES NOT TOUCH STOCK. The ingredient half of the same GRN writes a
 * PurchaseRecord, which IS the inventory receipt; an asset writes this
 * instead. `purchase_record_lines.ingredient_id` is still NOT NULL precisely
 * so the database refuses an asset if anyone ever wires the two together.
 */
class AssetReceiptFromGrnService
{
    /**
     * Create (or replace) the asset receipt behind a GRN.
     *
     * Keyed on the GRN rather than blindly inserted: a GRN can only be
     * confirmed while it is pending, but that is a rule in a form, and this
     * writes to the register — the count an outlet is audited against. One
     * receipt per GRN is a property worth holding here too.
     *
     * @param  array<int, array{asset_id:int, quantity:float, unit_cost:float}>  $lines
     *         Only lines actually received in a condition worth counting.
     * @return AssetMovement|null  null when the delivery had no assets on it.
     */
    public static function record(GoodsReceivedNote $grn, array $lines): ?AssetMovement
    {
        $lines = array_values(array_filter(
            $lines,
            fn ($l) => ! empty($l['asset_id']) && floatval($l['quantity']) > 0
        ));

        if ($lines === []) {
            // A GRN that had assets and now receives none of them must not
            // leave an earlier receipt standing.
            AssetMovement::where('goods_received_note_id', $grn->id)->get()
                ->each(function (AssetMovement $m) {
                    $m->lines()->delete();
                    $m->delete();
                });

            return null;
        }

        $total = 0;
        $prepared = [];

        foreach ($lines as $line) {
            $quantity  = round(floatval($line['quantity']), 4);
            $unitCost  = round(floatval($line['unit_cost']), 4);
            $lineTotal = round($quantity * $unitCost, 4);
            $total    += $lineTotal;

            $prepared[] = [
                'asset_id'   => (int) $line['asset_id'],
                'quantity'   => $quantity,
                'unit_cost'  => $unitCost,
                'total_cost' => $lineTotal,
                'notes'      => null,
            ];
        }

        $movement = AssetMovement::firstOrNew(['goods_received_note_id' => $grn->id]);

        $movement->fill([
            'movement_type'    => AssetMovement::TYPE_RECEIPT,
            'movement_date'    => $grn->received_date ?? now()->toDateString(),
            'reference_number' => $grn->grn_number,
            'supplier_id'      => $grn->supplier_id,
            'department_id'    => $grn->purchaseOrder?->department_id,
            'notes'            => 'Received on ' . $grn->grn_number,
            'total_cost'       => round($total, 4),
        ]);

        if (! $movement->exists) {
            $movement->company_id = $grn->company_id;
            $movement->outlet_id  = $grn->outlet_id;
            $movement->created_by = Auth::id() ?? $grn->created_by;
        }

        $movement->save();

        $movement->lines()->delete();

        foreach ($prepared as $line) {
            $movement->lines()->create($line);
        }

        self::writeBackCosts($prepared, $grn->supplier_id);

        return $movement;
    }

    /**
     * What the delivery cost becomes the asset's cost.
     *
     * Mirrors the hand-keyed receipt, INCLUDING its permission gate: the
     * catalogue price and the supplier's last price are cost data, and the
     * asset module has always kept those behind `assets.cost` rather than
     * behind whoever happened to sign for the box. Somebody receiving without
     * that ability still receives — the register counts the asset — the
     * catalogue price simply does not move.
     *
     * @param  array<int, array{asset_id:int, unit_cost:float}>  $lines
     */
    private static function writeBackCosts(array $lines, ?int $supplierId): void
    {
        if (! Auth::user()?->canDo('assets.cost')) {
            return;
        }

        foreach ($lines as $line) {
            $asset = Asset::find($line['asset_id']);

            if (! $asset) {
                continue;
            }

            $asset->update(['unit_cost' => $line['unit_cost']]);

            if ($supplierId) {
                $asset->supplierLinks()->updateOrCreate(
                    ['supplier_id' => (int) $supplierId],
                    ['last_cost' => $line['unit_cost']]
                );
            }
        }
    }
}
