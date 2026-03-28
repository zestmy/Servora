<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\GoodsReceivedNote;
use App\Models\ProcurementInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreditNoteService
{
    /**
     * Auto-generate a debit note from GRN variance (damaged/rejected/short delivery).
     */
    public static function generateFromGrn(GoodsReceivedNote $grn): ?CreditNote
    {
        $grn->loadMissing('lines.ingredient', 'lines.uom');

        $varianceLines = [];

        foreach ($grn->lines as $line) {
            $expected = floatval($line->expected_quantity);
            $received = floatval($line->received_quantity);
            $unitCost = floatval($line->unit_cost);

            if ($line->condition === 'damaged') {
                $varianceLines[] = [
                    'ingredient_id' => $line->ingredient_id,
                    'description'   => $line->ingredient?->name . ' — Damaged',
                    'quantity'      => $received,
                    'uom_id'       => $line->uom_id,
                    'unit_price'   => $unitCost,
                    'total_price'  => round($received * $unitCost, 4),
                    'reason_code'  => 'damaged',
                ];
            } elseif ($line->condition === 'rejected') {
                $varianceLines[] = [
                    'ingredient_id' => $line->ingredient_id,
                    'description'   => $line->ingredient?->name . ' — Rejected',
                    'quantity'      => $received > 0 ? $received : $expected,
                    'uom_id'       => $line->uom_id,
                    'unit_price'   => $unitCost,
                    'total_price'  => round(($received > 0 ? $received : $expected) * $unitCost, 4),
                    'reason_code'  => 'rejected',
                ];
            } elseif ($received < $expected && $line->condition === 'good') {
                $shortQty = $expected - $received;
                $varianceLines[] = [
                    'ingredient_id' => $line->ingredient_id,
                    'description'   => $line->ingredient?->name . ' — Short delivery',
                    'quantity'      => $shortQty,
                    'uom_id'       => $line->uom_id,
                    'unit_price'   => $unitCost,
                    'total_price'  => round($shortQty * $unitCost, 4),
                    'reason_code'  => 'short_delivery',
                ];
            }
        }

        if (empty($varianceLines)) return null;

        return DB::transaction(function () use ($grn, $varianceLines) {
            $subtotal = collect($varianceLines)->sum('total_price');

            $cn = CreditNote::create([
                'company_id'              => $grn->company_id,
                'credit_note_number'      => CreditNote::generateNumber('debit_note'),
                'type'                    => 'debit_note',
                'direction'               => 'issued',
                'status'                  => 'draft',
                'supplier_id'             => $grn->supplier_id,
                'outlet_id'               => $grn->outlet_id,
                'goods_received_note_id'  => $grn->id,
                'purchase_order_id'       => $grn->purchase_order_id,
                'issued_date'             => now()->toDateString(),
                'subtotal'                => round($subtotal, 4),
                'tax_amount'              => 0,
                'total_amount'            => round($subtotal, 4),
                'reason'                  => "Auto-generated from GRN {$grn->grn_number} variance",
                'created_by'              => Auth::id() ?? $grn->created_by ?? 1,
            ]);

            foreach ($varianceLines as $line) {
                CreditNoteLine::create(array_merge($line, ['credit_note_id' => $cn->id]));
            }

            return $cn;
        });
    }

    /**
     * Apply credit note to linked procurement invoice — offset the balance.
     */
    public static function applyToInvoice(CreditNote $cn): void
    {
        if ($cn->status === 'applied') return;

        $invoice = $cn->procurementInvoice;

        if ($invoice) {
            $newCreditApplied = floatval($invoice->credit_applied) + floatval($cn->total_amount);
            $balanceDue = max(0, floatval($invoice->total_amount) - $newCreditApplied);

            $invoice->update([
                'credit_applied' => round($newCreditApplied, 4),
                'balance_due'    => round($balanceDue, 4),
            ]);
        }

        $cn->update(['status' => 'applied']);
    }
}
