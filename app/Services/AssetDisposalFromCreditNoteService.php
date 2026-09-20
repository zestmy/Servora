<?php

namespace App\Services;

use App\Models\AssetMovement;
use App\Models\CreditNote;

/**
 * Taking a credited-back asset out of the register.
 *
 * The mirror of AssetReceiptFromGrnService: receiving a delivery puts an
 * asset into the register, and crediting one back takes it out again,
 * without anybody keying the second document by hand.
 *
 * TWO REASON CODES, AND ONLY TWO. A credit note's reason codes disagree
 * about what physically happened, so only the ones that describe an asset
 * leaving an outlet it was actually holding may move the register:
 *
 *   return          the asset went back to the supplier          → dispose
 *   damaged         it arrived damaged and is going back         → dispose
 *   rejected        it was refused at the door and NEVER entered → no
 *   short_delivery  it never arrived at all                      → no
 *   overcharge      money only, the asset is still here          → no
 *   other           unknown; guessing here would be a write      → no
 *
 * The two `no` cases at the top are the ones that matter: a GRN only ever
 * receives what actually arrived in a countable condition, so a rejected or
 * short line was never in the register in the first place. Disposing on
 * those would subtract a second time and quietly understate what an outlet
 * holds — on the number it is audited against.
 *
 * ONLY WHEN THE NOTE IS ISSUED. A draft is a piece of thinking, not a
 * decision, and it must not move stock or the register.
 */
class AssetDisposalFromCreditNoteService
{
    /** Reason codes that describe an asset physically leaving the outlet. */
    public const DISPOSING_REASONS = ['return', 'damaged'];

    /**
     * Create, update or remove the disposal behind a credit note.
     *
     * Keyed on the note so that issuing it twice cannot subtract twice, and
     * so that a note whose qualifying lines are taken away does not leave an
     * earlier disposal standing.
     *
     * @return AssetMovement|null  null when the note disposes of nothing.
     */
    public static function sync(CreditNote $creditNote): ?AssetMovement
    {
        $existing = AssetMovement::where('credit_note_id', $creditNote->id)->first();

        $qualifying = $creditNote->status === 'issued'
            ? $creditNote->lines
                ->filter(fn ($l) => $l->asset_id
                    && in_array($l->reason_code, self::DISPOSING_REASONS, true)
                    && floatval($l->quantity) > 0)
            : collect();

        if ($qualifying->isEmpty()) {
            if ($existing) {
                $existing->lines()->delete();
                $existing->delete();
            }

            return null;
        }

        $total    = 0;
        $prepared = [];

        foreach ($qualifying as $line) {
            $quantity  = round(floatval($line->quantity), 4);
            $unitCost  = round(floatval($line->unit_price), 4);
            $lineTotal = round($quantity * $unitCost, 4);
            $total    += $lineTotal;

            $prepared[] = [
                'asset_id'   => (int) $line->asset_id,
                'quantity'   => $quantity,
                'unit_cost'  => $unitCost,
                'total_cost' => $lineTotal,
                'notes'      => $line->description ?: null,
            ];
        }

        $movement = $existing ?: new AssetMovement(['credit_note_id' => $creditNote->id]);

        $movement->fill([
            'credit_note_id'   => $creditNote->id,
            'movement_type'    => AssetMovement::TYPE_DISPOSAL,
            'movement_date'    => $creditNote->issued_date ?? now()->toDateString(),
            'reference_number' => $creditNote->credit_note_number,
            // 'returned' covers both codes: on a credit note the supplier is
            // taking it back, which is what a damaged line means here too.
            'reason'           => 'returned',
            'notes'            => 'Credited back on ' . $creditNote->credit_note_number,
            'total_cost'       => round($total, 4),
        ]);

        if (! $movement->exists) {
            $movement->company_id = $creditNote->company_id;
            $movement->outlet_id  = $creditNote->outlet_id;
            $movement->created_by = $creditNote->created_by;
        }

        $movement->save();

        $movement->lines()->delete();

        foreach ($prepared as $line) {
            $movement->lines()->create($line);
        }

        return $movement;
    }
}
