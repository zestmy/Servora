<?php

namespace App\Services;

use App\Models\AiInvoiceScan;
use App\Models\GoodsReceivedNote;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementInvoiceLine;
use App\Models\ProcurementInvoicePayment;
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
                'balance_due'             => $totalAmount,
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
     * Record a payment against an invoice and update its status/balance.
     * Overpayment is rejected — amount must not exceed the outstanding balance.
     */
    public static function recordPayment(ProcurementInvoice $invoice, array $data): ProcurementInvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = ProcurementInvoice::lockForUpdate()->findOrFail($invoice->id);

            $outstanding = $invoice->outstanding();
            $amount = round(floatval($data['amount']), 4);

            if ($amount <= 0) {
                throw new \InvalidArgumentException('Payment amount must be greater than zero.');
            }
            if ($amount > $outstanding + 0.005) {
                throw new \InvalidArgumentException(
                    'Payment of ' . number_format($amount, 2) . ' exceeds the outstanding balance of ' . number_format($outstanding, 2) . '.'
                );
            }

            $payment = ProcurementInvoicePayment::create([
                'company_id'             => $invoice->company_id,
                'procurement_invoice_id' => $invoice->id,
                'payment_date'           => $data['payment_date'],
                'amount'                 => $amount,
                'method'                 => $data['method'] ?? 'bank_transfer',
                'reference'              => $data['reference'] ?? null,
                'notes'                  => $data['notes'] ?? null,
                'recorded_by'            => Auth::id(),
            ]);

            self::recalculate($invoice);

            return $payment;
        });
    }

    /**
     * Remove a mistaken payment and roll the invoice status/balance back.
     */
    public static function removePayment(ProcurementInvoicePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = ProcurementInvoice::lockForUpdate()->findOrFail($payment->procurement_invoice_id);
            $payment->delete();
            self::recalculate($invoice);
        });
    }

    /**
     * Mark invoice as fully paid by recording a payment for the whole
     * outstanding balance (keeps the payment trail intact).
     */
    public static function markPaid(ProcurementInvoice $invoice): void
    {
        $outstanding = $invoice->outstanding();

        if ($outstanding <= 0) {
            // Nothing owed (e.g. fully credited) — just settle the status.
            DB::transaction(fn () => self::recalculate(ProcurementInvoice::lockForUpdate()->findOrFail($invoice->id)));
            return;
        }

        self::recordPayment($invoice, [
            'payment_date' => now()->toDateString(),
            'amount'       => $outstanding,
            'method'       => 'other',
            'notes'        => 'Marked as paid (full outstanding balance)',
        ]);
    }

    /**
     * Recompute balance_due and settle the status from payments + credit.
     * Draft and cancelled invoices keep their status; only the balance moves.
     */
    public static function recalculate(ProcurementInvoice $invoice): void
    {
        $paid = (float) $invoice->payments()->sum('amount');
        $balance = max(0, round(floatval($invoice->total_amount) - floatval($invoice->credit_applied) - $paid, 4));

        $update = ['balance_due' => $balance];

        if (! in_array($invoice->status, ['draft', 'cancelled'])) {
            if ($balance <= 0.005) {
                $update['status'] = 'paid';
                $update['balance_due'] = 0;
            } elseif ($paid > 0) {
                $update['status'] = 'partial';
            } elseif (in_array($invoice->status, ['paid', 'partial'])) {
                // Payments were removed — fall back to issued/overdue.
                $update['status'] = ($invoice->due_date && $invoice->due_date->isPast()) ? 'overdue' : 'issued';
            }
        }

        $invoice->update($update);
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
                'balance_due'             => round($headerData['total_amount'], 4),
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
