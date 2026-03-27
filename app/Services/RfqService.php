<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestSupplier;
use App\Models\SupplierQuotation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RfqService
{
    /**
     * Send an RFQ to all selected suppliers — updates statuses and sends notifications.
     */
    public static function send(QuotationRequest $rfq): void
    {
        $rfq->suppliers()->update(['status' => 'pending', 'sent_at' => now()]);
        $rfq->update(['status' => 'sent']);

        // Notify each supplier
        foreach ($rfq->suppliers()->with('supplier')->get() as $rqs) {
            if ($rqs->supplier?->email) {
                SupplierNotificationService::notifyRfq($rqs->supplier, $rfq);
            }
        }
    }

    /**
     * Accept a supplier quotation and convert it into a Purchase Order.
     */
    public static function acceptAndCreatePo(SupplierQuotation $quotation): PurchaseOrder
    {
        return DB::transaction(function () use ($quotation) {
            $quotation->loadMissing(['lines', 'quotationRequest']);
            $rfq = $quotation->quotationRequest;

            // Mark quotation as accepted, others as rejected
            $quotation->update(['status' => 'accepted']);
            SupplierQuotation::where('quotation_request_id', $rfq->id)
                ->where('id', '!=', $quotation->id)
                ->update(['status' => 'rejected']);

            // Close the RFQ
            $rfq->update(['status' => 'closed']);

            // Generate PO
            $poNumber = 'PO-' . Carbon::now()->format('Ymd') . '-';
            $lastPo = PurchaseOrder::withoutGlobalScopes()
                ->where('po_number', 'like', "{$poNumber}%")
                ->orderByDesc('po_number')
                ->value('po_number');
            $seq = $lastPo ? ((int) substr($lastPo, strrpos($lastPo, '-') + 1) + 1) : 1;
            $poNumber .= str_pad($seq, 3, '0', STR_PAD_LEFT);

            $subtotal = $quotation->lines->sum(fn ($l) => floatval($l->total_price));

            $po = PurchaseOrder::create([
                'company_id'          => $rfq->company_id,
                'outlet_id'           => $rfq->outlet_id ?? Auth::user()->activeOutletId(),
                'supplier_id'         => $quotation->supplier_id,
                'po_number'           => $poNumber,
                'status'              => 'draft',
                'order_date'          => Carbon::today(),
                'subtotal'            => round($subtotal, 4),
                'tax_amount'          => floatval($quotation->tax_amount),
                'tax_percent'         => 0,
                'delivery_charges'    => floatval($quotation->delivery_charges),
                'total_amount'        => floatval($quotation->total_amount),
                'notes'               => "Created from RFQ {$rfq->rfq_number}, Quotation {$quotation->quotation_number}",
                'created_by'          => Auth::id(),
                'source'              => 'direct',
            ]);

            foreach ($quotation->lines as $line) {
                PurchaseOrderLine::create([
                    'purchase_order_id' => $po->id,
                    'ingredient_id'     => $line->ingredient_id,
                    'quantity'          => $line->quantity,
                    'uom_id'           => $line->uom_id,
                    'unit_cost'        => $line->unit_price,
                    'total_cost'       => $line->total_price,
                    'received_quantity' => 0,
                ]);
            }

            return $po;
        });
    }
}
