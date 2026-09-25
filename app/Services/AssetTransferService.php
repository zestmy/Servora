<?php

namespace App\Services;

use App\Models\AssetMovement;
use App\Models\OutletTransfer;
use Illuminate\Support\Facades\Auth;

/**
 * Moving assets between outlets on an ordinary transfer.
 *
 * An asset leaving one outlet for another is two register entries — a
 * disposal where it left and a receipt where it arrived — exactly the pair
 * somebody would otherwise key by hand under Assets. The transfer writes them
 * instead, tied back to it by `outlet_transfer_id`, so AssetOnHandService
 * stays the only thing that works out what an outlet holds.
 *
 * The disposal is written when the transfer is SENT and the receipt when it
 * is RECEIVED. Plates in a van are at neither branch: a count at the source
 * while they are in transit should not expect them, and neither should one at
 * the destination.
 *
 * Every method is keyed on the transfer and the movement type, so calling one
 * twice replaces rather than doubles. Ingredient, recipe and custom lines are
 * not touched here — this is the asset register only.
 */
class AssetTransferService
{
    /** Reason recorded on the source outlet's disposal. */
    public const REASON = 'transferred';

    /** The transfer left: take its assets off the source outlet. */
    public static function dispatch(OutletTransfer $transfer): ?AssetMovement
    {
        return self::write($transfer, AssetMovement::TYPE_DISPOSAL, (int) $transfer->from_outlet_id);
    }

    /** The transfer arrived: put its assets on the destination outlet. */
    public static function arrive(OutletTransfer $transfer): ?AssetMovement
    {
        // A transfer sent before it could carry assets, or edited in a way
        // that skipped the send, still leaves the source in step.
        self::dispatch($transfer);

        return self::write($transfer, AssetMovement::TYPE_RECEIPT, (int) $transfer->to_outlet_id);
    }

    /** Cancelled or deleted: the assets never moved, so neither entry stands. */
    public static function release(OutletTransfer $transfer): void
    {
        AssetMovement::withoutGlobalScopes()
            ->where('outlet_transfer_id', $transfer->id)
            ->get()
            ->each(function (AssetMovement $m) {
                $m->lines()->delete();
                $m->delete();
            });
    }

    private static function write(OutletTransfer $transfer, string $type, int $outletId): ?AssetMovement
    {
        $lines = $transfer->lines()->with('asset')->whereNotNull('asset_id')->get()
            ->filter(fn ($l) => $l->asset && floatval($l->quantity) > 0);

        if ($lines->isEmpty()) {
            return null;
        }

        $prepared = $lines->map(function ($l) {
            $quantity = round(floatval($l->quantity), 4);
            $unitCost = round(floatval($l->unit_cost), 4);

            return [
                'asset_id'   => (int) $l->asset_id,
                'quantity'   => $quantity,
                'unit_cost'  => $unitCost,
                'total_cost' => round($quantity * $unitCost, 4),
                'notes'      => null,
            ];
        })->values();

        $movement = AssetMovement::withoutGlobalScopes()->firstOrNew([
            'outlet_transfer_id' => $transfer->id,
            'movement_type'      => $type,
        ]);

        $isReceipt = $type === AssetMovement::TYPE_RECEIPT;

        $movement->fill([
            'movement_date'    => now()->toDateString(),
            'reference_number' => $transfer->transfer_number,
            'reason'           => $isReceipt ? null : self::REASON,
            'notes'            => ($isReceipt ? 'Received on ' : 'Sent on ') . $transfer->transfer_number,
            'total_cost'       => round($prepared->sum('total_cost'), 4),
        ]);

        // The outlet follows the transfer, in case its ends were changed.
        $movement->outlet_id = $outletId;

        if (! $movement->exists) {
            $movement->company_id = $transfer->company_id;
            $movement->created_by = Auth::id() ?? $transfer->created_by;
        }

        $movement->save();

        $movement->lines()->delete();

        foreach ($prepared as $line) {
            $movement->lines()->create($line);
        }

        return $movement;
    }
}
