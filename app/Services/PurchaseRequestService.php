<?php

namespace App\Services;

use App\Models\CentralPurchasingUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\SupplierIngredient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseRequestService
{
    /**
     * Generate a unique PR number: PR-YYYYMMDD-NNN
     */
    public static function generatePrNumber(): string
    {
        $date = Carbon::now()->format('Ymd');
        $prefix = "PR-{$date}-";

        $latest = PurchaseRequest::withoutGlobalScopes()
            ->where('pr_number', 'like', "{$prefix}%")
            ->orderByDesc('pr_number')
            ->value('pr_number');

        $sequence = 1;
        if ($latest) {
            $sequence = (int) substr($latest, strrpos($latest, '-') + 1) + 1;
        }

        return $prefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Consolidate approved PRs by supplier and generate POs.
     *
     * Groups PR lines by preferred_supplier_id, merges same-ingredient quantities,
     * and creates one PO per supplier.
     *
     * @param  Collection|array  $purchaseRequestIds
     * @param  int  $cpuId
     * @return array  Created PurchaseOrder IDs
     */
    public static function consolidate(array $purchaseRequestIds, int $cpuId): array
    {
        return DB::transaction(function () use ($purchaseRequestIds, $cpuId) {
            $cpu = CentralPurchasingUnit::findOrFail($cpuId);
            $prs = PurchaseRequest::with('lines.ingredient', 'lines.uom')
                ->whereIn('id', $purchaseRequestIds)
                ->where('status', PurchaseRequest::STATUS_APPROVED)
                ->get();

            if ($prs->isEmpty()) {
                return [];
            }

            // Separate kitchen items from supplier items
            $linesBySupplier = collect();
            $kitchenLines = collect();

            foreach ($prs as $pr) {
                foreach ($pr->lines as $line) {
                    if (! $line->ingredient_id) continue; // skip custom items

                    if ($line->source === 'kitchen') {
                        $kitchenLines->push([
                            'pr_id'         => $pr->id,
                            'outlet_id'     => $pr->outlet_id,
                            'ingredient_id' => $line->ingredient_id,
                            'quantity'      => $line->quantity,
                            'uom_id'        => $line->uom_id,
                            'ingredient'    => $line->ingredient,
                            'kitchen_id'    => $line->kitchen_id,
                            'recipe_id'     => $line->ingredient?->prep_recipe_id,
                        ]);
                        continue;
                    }

                    $supplierId = $line->preferred_supplier_id ?? 0;
                    if (!$linesBySupplier->has($supplierId)) {
                        $linesBySupplier[$supplierId] = collect();
                    }
                    $linesBySupplier[$supplierId]->push([
                        'pr_id'         => $pr->id,
                        'outlet_id'     => $pr->outlet_id,
                        'ingredient_id' => $line->ingredient_id,
                        'quantity'      => $line->quantity,
                        'uom_id'        => $line->uom_id,
                        'ingredient'    => $line->ingredient,
                    ]);
                }
            }

            // Create Production Orders for kitchen items
            if ($kitchenLines->isNotEmpty()) {
                $kitchenGroups = $kitchenLines->groupBy('kitchen_id');
                foreach ($kitchenGroups as $kitchenId => $lines) {
                    if (! $kitchenId) continue;
                    $prodOrder = \App\Models\ProductionOrder::create([
                        'company_id'      => $companyId,
                        'kitchen_id'      => $kitchenId,
                        'order_number'    => \App\Models\ProductionOrder::generateNumber(),
                        'status'          => 'scheduled',
                        'production_date' => now()->addDay()->toDateString(),
                        'notes'           => 'Auto-created from PR consolidation',
                        'created_by'      => $userId,
                    ]);
                    foreach ($lines as $l) {
                        if (! $l['recipe_id']) continue;
                        \App\Models\ProductionOrderLine::create([
                            'production_order_id' => $prodOrder->id,
                            'recipe_id'           => $l['recipe_id'],
                            'planned_quantity'    => $l['quantity'],
                            'uom_id'             => $l['uom_id'],
                            'unit_cost'          => floatval($l['ingredient']?->current_cost ?? 0),
                            'to_outlet_id'       => $l['outlet_id'],
                            'status'             => 'pending',
                        ]);
                    }
                    $createdPoIds[] = -$prodOrder->id; // negative to distinguish from POs
                }
            }

            $createdPoIds = [];
            $companyId = $prs->first()->company_id;
            $userId = Auth::id();

            foreach ($linesBySupplier as $supplierId => $lines) {
                if ($supplierId === 0) {
                    // Lines without supplier preference — skip or assign later
                    continue;
                }

                // Merge same ingredient quantities
                $merged = $lines->groupBy('ingredient_id')->map(function ($group) {
                    $first = $group->first();
                    return [
                        'ingredient_id' => $first['ingredient_id'],
                        'quantity'      => $group->sum('quantity'),
                        'uom_id'       => $first['uom_id'],
                        'ingredient'    => $first['ingredient'],
                    ];
                });

                // Determine delivery outlet (if all lines from same outlet, use that; otherwise CPU decides)
                $outletIds = $lines->pluck('outlet_id')->unique();
                $deliveryOutletId = $outletIds->count() === 1 ? $outletIds->first() : null;

                // Look up supplier costs
                $supplierCosts = SupplierIngredient::where('supplier_id', $supplierId)
                    ->whereIn('ingredient_id', $merged->pluck('ingredient_id'))
                    ->pluck('last_cost', 'ingredient_id');

                // Generate PO number
                $date = Carbon::now()->format('Ymd');
                $poPrefix = "PO-{$date}-";
                $latestPo = PurchaseOrder::withoutGlobalScopes()
                    ->where('po_number', 'like', "{$poPrefix}%")
                    ->orderByDesc('po_number')
                    ->value('po_number');
                $poSeq = 1;
                if ($latestPo) {
                    $poSeq = (int) substr($latestPo, strrpos($latestPo, '-') + 1) + 1;
                }
                $poNumber = $poPrefix . str_pad($poSeq, 3, '0', STR_PAD_LEFT);

                // Determine the outlet_id for the PO (use first requesting outlet or CPU-related)
                $poOutletId = $outletIds->first();

                $subtotal = 0;
                $poLines = [];
                foreach ($merged as $item) {
                    $unitCost = $supplierCosts[$item['ingredient_id'] ?? 0]
                        ?? ($item['ingredient']?->purchase_price ?? 0);
                    $totalCost = round($item['quantity'] * $unitCost, 4);
                    $subtotal += $totalCost;

                    $poLines[] = [
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity'      => $item['quantity'],
                        'uom_id'       => $item['uom_id'],
                        'unit_cost'    => $unitCost,
                        'total_cost'   => $totalCost,
                    ];
                }

                $po = PurchaseOrder::create([
                    'company_id'          => $companyId,
                    'outlet_id'           => $poOutletId,
                    'supplier_id'         => $supplierId,
                    'po_number'           => $poNumber,
                    'status'              => 'draft',
                    'order_date'          => Carbon::today(),
                    'subtotal'            => $subtotal,
                    'total_amount'        => $subtotal,
                    'tax_percent'         => 0,
                    'tax_amount'          => 0,
                    'delivery_charges'    => 0,
                    'created_by'          => $userId,
                    'purchase_request_id' => $prs->count() === 1 ? $prs->first()->id : null,
                    'cpu_id'              => $cpuId,
                    'source'              => 'cpu_consolidated',
                    'delivery_outlet_id'  => $deliveryOutletId,
                ]);

                foreach ($poLines as $line) {
                    PurchaseOrderLine::create(array_merge($line, [
                        'purchase_order_id' => $po->id,
                    ]));
                }

                $createdPoIds[] = $po->id;
            }

            // Mark PRs as converted
            PurchaseRequest::whereIn('id', $purchaseRequestIds)
                ->update(['status' => PurchaseRequest::STATUS_CONVERTED]);

            return $createdPoIds;
        });
    }

    /**
     * Get a preview of what consolidation will produce.
     * Groups lines by supplier and merges quantities.
     *
     * @return Collection  Keyed by supplier_id, each containing merged lines
     */
    public static function consolidationPreview(array $purchaseRequestIds): Collection
    {
        $prs = PurchaseRequest::with('lines.ingredient', 'lines.preferredSupplier', 'lines.uom', 'outlet')
            ->whereIn('id', $purchaseRequestIds)
            ->where('status', PurchaseRequest::STATUS_APPROVED)
            ->get();

        $grouped = collect();

        foreach ($prs as $pr) {
            foreach ($pr->lines as $line) {
                $supplierId = $line->preferred_supplier_id ?? 0;
                if (!$grouped->has($supplierId)) {
                    $grouped[$supplierId] = [
                        'supplier'    => $line->preferredSupplier,
                        'lines'       => collect(),
                        'outlet_ids'  => collect(),
                    ];
                }

                $existing = $grouped[$supplierId]['lines']->firstWhere('ingredient_id', $line->ingredient_id);
                if ($existing) {
                    $existing['quantity'] += $line->quantity;
                } else {
                    $grouped[$supplierId]['lines']->push([
                        'ingredient_id'   => $line->ingredient_id,
                        'ingredient_name' => $line->ingredient?->name ?? $line->custom_name ?? '—',
                        'quantity'        => $line->quantity,
                        'uom'            => $line->uom?->abbreviation ?? $line->uom?->name ?? '',
                    ]);
                }

                $grouped[$supplierId]['outlet_ids']->push($pr->outlet_id);
            }
        }

        return $grouped;
    }
}
