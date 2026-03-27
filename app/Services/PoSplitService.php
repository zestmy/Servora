<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SupplierIngredient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PoSplitService
{
    /**
     * Split a set of order lines (with mixed suppliers) into separate POs per supplier.
     *
     * @param  array  $lines  [{ingredient_id, quantity, uom_id, unit_cost, supplier_id, supplier_sku, supplier_product_name}]
     * @param  array  $headerData  {company_id, outlet_id, order_date, expected_delivery_date, notes, tax_percent, status, created_by, ...}
     * @return array  Created PurchaseOrder IDs
     */
    public static function splitAndCreate(array $lines, array $headerData): array
    {
        return DB::transaction(function () use ($lines, $headerData) {
            // Group lines by supplier_id
            $grouped = collect($lines)->groupBy(function ($line) {
                return $line['supplier_id'] ?? 0;
            });

            $createdPoIds = [];

            foreach ($grouped as $supplierId => $supplierLines) {
                if (! $supplierId) continue; // skip lines without supplier

                $subtotal = 0;
                $poLines = [];

                foreach ($supplierLines as $line) {
                    $qty = floatval($line['quantity']);
                    $cost = floatval($line['unit_cost']);
                    $total = round($qty * $cost, 4);
                    $subtotal += $total;

                    $poLines[] = [
                        'ingredient_id'         => $line['ingredient_id'],
                        'supplier_sku'          => $line['supplier_sku'] ?? null,
                        'supplier_product_name' => $line['supplier_product_name'] ?? null,
                        'quantity'              => $qty,
                        'uom_id'               => $line['uom_id'],
                        'unit_cost'            => $cost,
                        'total_cost'           => $total,
                        'received_quantity'     => 0,
                    ];
                }

                $taxPct = floatval($headerData['tax_percent'] ?? 0);
                $taxAmt = $taxPct > 0 ? round($subtotal * ($taxPct / 100), 4) : 0;
                $total = round($subtotal + $taxAmt, 4);

                $po = PurchaseOrder::create(array_merge($headerData, [
                    'supplier_id'  => $supplierId,
                    'po_number'    => self::generatePoNumber(),
                    'subtotal'     => $subtotal,
                    'tax_amount'   => $taxAmt,
                    'total_amount' => $total,
                ]));

                foreach ($poLines as $poLine) {
                    PurchaseOrderLine::create(array_merge($poLine, [
                        'purchase_order_id' => $po->id,
                    ]));
                }

                $createdPoIds[] = $po->id;
            }

            return $createdPoIds;
        });
    }

    private static function generatePoNumber(): string
    {
        $prefix = 'PO-' . Carbon::now()->format('Ymd') . '-';
        $last = PurchaseOrder::withoutGlobalScopes()
            ->where('po_number', 'like', "{$prefix}%")
            ->orderByDesc('po_number')
            ->value('po_number');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;
        return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
