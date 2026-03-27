<?php

namespace App\Services;

use App\Models\GoodsReceivedNote;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementInvoiceLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProcurementInvoiceService
{
    /**
     * Auto-create a supplier invoice from a received GRN.
     */
    public static function createFromGrn(GoodsReceivedNote $grn): ProcurementInvoice
    {
        return DB::transaction(function () use ($grn) {
            $grn->loadMissing('lines');

            $subtotal = $grn->lines->sum(fn ($l) => floatval($l->total_cost));

            $taxResult = TaxCalculationService::calculate(
                $subtotal,
                $grn->tax_rate_id,
                $grn->company
            );

            $deliveryCharges = floatval($grn->delivery_charges ?? 0);
            $totalAmount = TaxCalculationService::grandTotal($subtotal, $taxResult['tax_amount'], $deliveryCharges);

            $invoice = ProcurementInvoice::create([
                'company_id'              => $grn->company_id,
                'outlet_id'               => $grn->outlet_id,
                'supplier_id'             => $grn->supplier_id,
                'goods_received_note_id'  => $grn->id,
                'purchase_order_id'       => $grn->purchase_order_id,
                'invoice_number'          => ProcurementInvoice::generateNumber(),
                'type'                    => 'supplier',
                'status'                  => 'issued',
                'issued_date'             => $grn->received_date ?? now(),
                'due_date'                => ($grn->received_date ?? now())->addDays(30),
                'subtotal'                => round($subtotal, 4),
                'tax_rate_id'             => $grn->tax_rate_id,
                'tax_amount'              => $taxResult['tax_amount'],
                'delivery_charges'        => $deliveryCharges,
                'total_amount'            => $totalAmount,
                'notes'                   => "Auto-generated from GRN {$grn->grn_number}",
                'created_by'              => Auth::id() ?? $grn->created_by,
            ]);

            foreach ($grn->lines as $line) {
                if (floatval($line->received_quantity) <= 0) continue;

                ProcurementInvoiceLine::create([
                    'procurement_invoice_id' => $invoice->id,
                    'ingredient_id'          => $line->ingredient_id,
                    'quantity'               => $line->received_quantity,
                    'uom_id'                => $line->uom_id,
                    'unit_price'            => $line->unit_cost,
                    'total_price'           => $line->total_cost,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * Mark invoice as paid.
     */
    public static function markPaid(ProcurementInvoice $invoice): void
    {
        $invoice->update(['status' => 'paid']);
    }

    /**
     * Cancel an invoice.
     */
    public static function cancel(ProcurementInvoice $invoice): void
    {
        $invoice->update(['status' => 'cancelled']);
    }
}
