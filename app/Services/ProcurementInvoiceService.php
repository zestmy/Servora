<?php

namespace App\Services;

use App\Models\AiInvoiceScan;
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

    /**
     * Create a procurement invoice from AI-scanned data.
     */
    public static function createFromAiScan(array $headerData, array $lines, AiInvoiceScan $scan): ProcurementInvoice
    {
        return DB::transaction(function () use ($headerData, $lines, $scan) {
            $invoice = ProcurementInvoice::create([
                'company_id'              => $headerData['company_id'],
                'outlet_id'               => $headerData['outlet_id'],
                'supplier_id'             => $headerData['supplier_id'],
                'purchase_order_id'       => $headerData['purchase_order_id'] ?? null,
                'goods_received_note_id'  => $headerData['goods_received_note_id'] ?? null,
                'invoice_number'          => ProcurementInvoice::generateNumber(),
                'supplier_invoice_number' => $headerData['supplier_invoice_number'] ?? null,
                'type'                    => 'supplier',
                'status'                  => 'issued',
                'issued_date'             => $headerData['issued_date'] ?? now(),
                'due_date'                => $headerData['due_date'] ?? null,
                'subtotal'                => round($headerData['subtotal'], 4),
                'tax_rate_id'             => $headerData['tax_rate_id'] ?? null,
                'tax_amount'              => round($headerData['tax_amount'] ?? 0, 4),
                'delivery_charges'        => round($headerData['delivery_charges'] ?? 0, 4),
                'total_amount'            => round($headerData['total_amount'], 4),
                'original_file_path'      => $scan->original_file_path,
                'ai_invoice_scan_id'      => $scan->id,
                'notes'                   => $headerData['notes'] ?? null,
                'created_by'              => Auth::id(),
            ]);

            foreach ($lines as $line) {
                ProcurementInvoiceLine::create([
                    'procurement_invoice_id' => $invoice->id,
                    'ingredient_id'          => $line['ingredient_id'] ?? null,
                    'description'            => $line['description'] ?? null,
                    'quantity'               => $line['quantity'],
                    'uom_id'                => $line['uom_id'] ?? null,
                    'unit_price'            => $line['unit_price'],
                    'total_price'           => round($line['quantity'] * $line['unit_price'], 4),
                ]);
            }

            $scan->update([
                'status'                  => 'approved',
                'procurement_invoice_id'  => $invoice->id,
            ]);

            return $invoice;
        });
    }
}
