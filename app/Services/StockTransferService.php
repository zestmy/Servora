<?php

namespace App\Services;

use App\Models\ProcurementInvoice;
use App\Models\ProcurementInvoiceLine;
use App\Models\StockTransferOrder;
use App\Models\StockTransferOrderLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public static function generateStoNumber(): string
    {
        $prefix = 'STO-' . Carbon::now()->format('Ymd') . '-';
        $latest = StockTransferOrder::withoutGlobalScopes()
            ->where('sto_number', 'like', "{$prefix}%")
            ->orderByDesc('sto_number')
            ->value('sto_number');

        $seq = 1;
        if ($latest) {
            $seq = (int) substr($latest, strrpos($latest, '-') + 1) + 1;
        }
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Create an STO with lines. Optionally auto-generates a procurement invoice if chargeable.
     *
     * @param  array  $data  STO header data
     * @param  array  $lines  Array of line items [{ingredient_id, quantity, uom_id, unit_cost}]
     * @return StockTransferOrder
     */
    public static function create(array $data, array $lines): StockTransferOrder
    {
        return DB::transaction(function () use ($data, $lines) {
            $subtotal = 0;
            foreach ($lines as $line) {
                $subtotal += round(floatval($line['quantity']) * floatval($line['unit_cost']), 4);
            }

            $isChargeable = $data['is_chargeable'] ?? false;
            $taxRateId = $data['tax_rate_id'] ?? null;
            $deliveryCharges = floatval($data['delivery_charges'] ?? 0);

            // Calculate tax
            $taxResult = TaxCalculationService::calculate(
                $isChargeable ? $subtotal : 0,
                $taxRateId
            );
            $taxAmount = $isChargeable ? $taxResult['tax_amount'] : 0;
            $totalAmount = $isChargeable
                ? TaxCalculationService::grandTotal($subtotal, $taxAmount, $deliveryCharges)
                : 0;

            $sto = StockTransferOrder::create([
                'company_id'        => $data['company_id'],
                'cpu_id'            => $data['cpu_id'],
                'to_outlet_id'      => $data['to_outlet_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'sto_number'        => $data['sto_number'] ?? self::generateStoNumber(),
                'status'            => $data['status'] ?? 'draft',
                'transfer_date'     => $data['transfer_date'],
                'is_chargeable'     => $isChargeable,
                'subtotal'          => $isChargeable ? $subtotal : 0,
                'tax_rate_id'       => $isChargeable ? $taxRateId : null,
                'tax_amount'        => $taxAmount,
                'delivery_charges'  => $isChargeable ? $deliveryCharges : 0,
                'total_amount'      => $totalAmount,
                'notes'             => $data['notes'] ?? null,
                'created_by'        => $data['created_by'] ?? Auth::id(),
            ]);

            foreach ($lines as $line) {
                $qty = floatval($line['quantity']);
                $cost = $isChargeable ? floatval($line['unit_cost']) : 0;
                StockTransferOrderLine::create([
                    'stock_transfer_order_id' => $sto->id,
                    'ingredient_id'           => $line['ingredient_id'],
                    'quantity'                => $qty,
                    'uom_id'                 => $line['uom_id'],
                    'unit_cost'              => $cost,
                    'total_cost'             => round($qty * $cost, 4),
                ]);
            }

            // Auto-generate procurement invoice for chargeable transfers
            if ($isChargeable) {
                self::generateInvoice($sto);
            }

            return $sto;
        });
    }

    /**
     * Generate a procurement invoice from a chargeable STO.
     */
    public static function generateInvoice(StockTransferOrder $sto): ProcurementInvoice
    {
        $sto->loadMissing('lines');

        $invoice = ProcurementInvoice::create([
            'company_id'              => $sto->company_id,
            'outlet_id'               => $sto->to_outlet_id,
            'stock_transfer_order_id' => $sto->id,
            'invoice_number'          => ProcurementInvoice::generateNumber(),
            'type'                    => 'cpu_to_outlet',
            'status'                  => 'issued',
            'issued_date'             => $sto->transfer_date,
            'due_date'                => $sto->transfer_date->addDays(30),
            'subtotal'                => $sto->subtotal,
            'tax_rate_id'             => $sto->tax_rate_id,
            'tax_amount'              => $sto->tax_amount,
            'delivery_charges'        => $sto->delivery_charges,
            'total_amount'            => $sto->total_amount,
            'notes'                   => "Auto-generated from STO {$sto->sto_number}",
            'created_by'              => $sto->created_by,
        ]);

        foreach ($sto->lines as $line) {
            ProcurementInvoiceLine::create([
                'procurement_invoice_id' => $invoice->id,
                'ingredient_id'          => $line->ingredient_id,
                'quantity'               => $line->quantity,
                'uom_id'                => $line->uom_id,
                'unit_price'            => $line->unit_cost,
                'total_price'           => $line->total_cost,
            ]);
        }

        return $invoice;
    }
}
