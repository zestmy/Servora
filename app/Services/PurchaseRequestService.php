<?php

namespace App\Services;

use App\Models\CentralPurchasingUnit;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\SupplierIngredient;
use App\Models\TaxRate;
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
    /**
     * What makes two consolidated lines the same line.
     *
     * Grouping on ingredient_id alone put every asset in the one null bucket,
     * so a consolidation covering a mixer and an oven merged them into a
     * single order line — the quantities added together and one of the two
     * simply stopped existing.
     */
    private static function mergeKey(array $line): string
    {
        $asset = $line['asset_id'] ?? null;

        return $asset ? 'asset:' . $asset : 'ingredient:' . (int) ($line['ingredient_id'] ?? 0);
    }

    /**
     * What a supplier last charged for an asset, keyed by asset id.
     *
     * Assets keep their prices in `asset_suppliers`, not in the
     * `supplier_ingredients` table the rest of this service reads — asking
     * the wrong one returns nothing, and every asset consolidates at zero.
     *
     * @param  array<int, int>  $assetIds
     * @return array<int, float>
     */
    private static function assetCosts(int $supplierId, array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        return \App\Models\AssetSupplier::where('supplier_id', $supplierId)
            ->whereIn('asset_id', $assetIds)
            ->pluck('last_cost', 'asset_id')
            ->map(fn ($c) => floatval($c))
            ->all();
    }
    public static function consolidate(array $purchaseRequestIds, int $cpuId): array
    {
        return DB::transaction(function () use ($purchaseRequestIds, $cpuId) {
            $cpu = CentralPurchasingUnit::findOrFail($cpuId);
            $prs = PurchaseRequest::with('lines.ingredient', 'lines.asset', 'lines.uom')
                ->whereIn('id', $purchaseRequestIds)
                ->where('status', PurchaseRequest::STATUS_APPROVED)
                ->get();

            if ($prs->isEmpty()) {
                return [];
            }

            $companyId = $prs->first()->company_id;
            $userId = Auth::id();

            // Create Production Orders for kitchen items (returns negative IDs)
            $createdPoIds = self::createKitchenProductionOrders($prs, $companyId, $userId);

            // Group remaining supplier items by preferred supplier
            $linesBySupplier = collect();

            foreach ($prs as $pr) {
                foreach ($pr->lines as $line) {
                    // An asset consolidates like anything else — it is bought
                    // from a supplier on a purchase order. It parts company at
                    // receiving, where it goes into the asset register rather
                    // than into stock; see AssetReceiptFromGrnService.
                    if (! $line->ingredient_id && ! $line->asset_id) continue;  // hand-typed items
                    if ($line->source === 'kitchen') continue;                  // handled above

                    $supplierId = $line->preferred_supplier_id ?? 0;
                    if (!$linesBySupplier->has($supplierId)) {
                        $linesBySupplier[$supplierId] = collect();
                    }
                    $linesBySupplier[$supplierId]->push([
                        'pr_id'         => $pr->id,
                        'outlet_id'     => $pr->outlet_id,
                        'ingredient_id' => $line->ingredient_id,
                        'asset_id'      => $line->asset_id,
                        'quantity'      => $line->quantity,
                        'uom_id'        => $line->uom_id,
                        'ingredient'    => $line->ingredient,
                        'asset'         => $line->asset,
                    ]);
                }
            }

            foreach ($linesBySupplier as $supplierId => $lines) {
                if ($supplierId === 0) {
                    // Lines without supplier preference — skip or assign later
                    continue;
                }

                // Merge same ingredient quantities
                $merged = $lines->groupBy(fn ($l) => self::mergeKey($l))->map(function ($group) {
                    $first = $group->first();
                    return [
                        'ingredient_id' => $first['ingredient_id'],
                        'asset_id'      => $first['asset_id'] ?? null,
                        'quantity'      => $group->sum('quantity'),
                        'uom_id'       => $first['uom_id'],
                        'ingredient'    => $first['ingredient'],
                        'asset'         => $first['asset'] ?? null,
                    ];
                });

                // Determine delivery outlet (if all lines from same outlet, use that; otherwise CPU decides)
                $outletIds = $lines->pluck('outlet_id')->unique();
                $deliveryOutletId = $outletIds->count() === 1 ? $outletIds->first() : null;

                // Look up supplier costs
                $supplierCosts = SupplierIngredient::where('supplier_id', $supplierId)
                    ->whereIn('ingredient_id', $merged->pluck('ingredient_id')->filter())
                    ->pluck('last_cost', 'ingredient_id');

                $assetCosts = self::assetCosts(
                    (int) $supplierId,
                    $merged->pluck('asset_id')->filter()->map(fn ($id) => (int) $id)->all()
                );

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
                    $unitCost = $item['asset_id']
                        ? ($assetCosts[(int) $item['asset_id']] ?? floatval($item['asset']?->unit_cost ?? 0))
                        : ($supplierCosts[$item['ingredient_id'] ?? 0] ?? ($item['ingredient']?->purchase_price ?? 0));
                    $totalCost = round($item['quantity'] * $unitCost, 4);
                    $subtotal += $totalCost;

                    $poLines[] = [
                        'ingredient_id' => $item['ingredient_id'],
                        'asset_id'      => $item['asset_id'],
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
     * Create Production Orders for the kitchen-sourced lines of the given PRs.
     * Grouped by kitchen. Returns the created production order IDs as negative
     * numbers so callers can distinguish them from real PO IDs.
     *
     * @param  Collection  $prs  PRs with `lines.ingredient` eager-loaded
     * @return array
     */
    private static function createKitchenProductionOrders(Collection $prs, int $companyId, ?int $userId): array
    {
        $kitchenLines = collect();

        foreach ($prs as $pr) {
            foreach ($pr->lines as $line) {
                if (! $line->ingredient_id) continue;
                if ($line->source !== 'kitchen') continue;

                $kitchenLines->push([
                    'outlet_id'  => $pr->outlet_id,
                    'quantity'   => $line->quantity,
                    'uom_id'     => $line->uom_id,
                    'ingredient' => $line->ingredient,
                    'kitchen_id' => $line->kitchen_id,
                    'recipe_id'  => $line->ingredient?->prep_recipe_id,
                ]);
            }
        }

        if ($kitchenLines->isEmpty()) {
            return [];
        }

        $createdIds = [];

        foreach ($kitchenLines->groupBy('kitchen_id') as $kitchenId => $lines) {
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
                    'uom_id'              => $l['uom_id'],
                    'unit_cost'           => floatval($l['ingredient']?->current_cost ?? 0),
                    'to_outlet_id'        => $l['outlet_id'],
                    'status'              => 'pending',
                ]);
            }

            $createdIds[] = -$prodOrder->id; // negative to distinguish from POs
        }

        return $createdIds;
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

    /**
     * Enhanced consolidation preview with cost lookup and supplier options.
     */
    public static function consolidationPreviewWithCosts(array $purchaseRequestIds): array
    {
        $prs = PurchaseRequest::with('lines.ingredient.taxRate', 'lines.asset', 'lines.preferredSupplier', 'lines.uom', 'outlet')
            ->whereIn('id', $purchaseRequestIds)
            ->where('status', PurchaseRequest::STATUS_APPROVED)
            ->get();

        $ingredientIds = $prs->flatMap(fn ($pr) => $pr->lines->pluck('ingredient_id'))->filter()->unique()->values();
        $company = Auth::user()->company;

        // Build cost lookup: [ingredient_id => [supplier_id => last_cost]]
        $costLookup = [];
        $supplierIngredients = SupplierIngredient::whereIn('ingredient_id', $ingredientIds)->get();
        foreach ($supplierIngredients as $si) {
            $costLookup[$si->ingredient_id][$si->supplier_id] = floatval($si->last_cost);
        }

        // The same, for assets. They price out of `asset_suppliers`, which is
        // a different table from the one above — reading the wrong one shows
        // every asset on the preview at zero.
        $assetIds = $prs->flatMap(fn ($pr) => $pr->lines->pluck('asset_id'))->filter()->unique()->values();
        $assetCostLookup = [];
        foreach (\App\Models\AssetSupplier::whereIn('asset_id', $assetIds)->get() as $as) {
            $assetCostLookup[$as->asset_id][$as->supplier_id] = floatval($as->last_cost);
        }

        // Tax info per ingredient
        $taxLookup = [];
        $defaultTax = TaxRate::defaultForCompany($company);
        foreach (Ingredient::withoutGlobalScopes()->whereIn('id', $ingredientIds)->with('taxRate')->get() as $ing) {
            $tr = $ing->tax_rate_id ? $ing->taxRate : $defaultTax;
            $taxLookup[$ing->id] = $tr ? [
                'id'    => $tr->id,
                'label' => $tr->name . ' ' . rtrim(rtrim(number_format($tr->rate, 2), '0'), '.') . '%',
                'rate'  => floatval($tr->rate),
            ] : null;
        }

        // Group by supplier
        $groups = [];
        $kitchenLineCount = 0;
        $assetLineCount   = 0;
        foreach ($prs as $pr) {
            foreach ($pr->lines as $line) {
                if ($line->asset_id) { $assetLineCount++; }
                if (! $line->ingredient_id && ! $line->asset_id) continue;
                if ($line->source === 'kitchen') { $kitchenLineCount++; continue; }

                $supplierId = $line->preferred_supplier_id ?? 0;
                if (! isset($groups[$supplierId])) {
                    $groups[$supplierId] = [
                        'supplier_id'   => $supplierId,
                        'supplier_name' => $line->preferredSupplier?->name ?? 'No Supplier',
                        'lines'         => [],
                        'outlet_ids'    => [],
                    ];
                }

                // Merge same ingredient
                $found = false;
                $lineKey = self::mergeKey([
                    'ingredient_id' => $line->ingredient_id,
                    'asset_id'      => $line->asset_id,
                ]);

                foreach ($groups[$supplierId]['lines'] as &$existing) {
                    // Comparing ingredient_id alone matched every asset line
                    // against every other, because they are all null.
                    if (self::mergeKey($existing) === $lineKey) {
                        $existing['quantity'] += floatval($line->quantity);
                        $found = true;
                        break;
                    }
                }
                unset($existing);

                if (! $found) {
                    $isAsset = (bool) $line->asset_id;

                    // An asset prices off its own supplier link, falling back
                    // to the catalogue cost, and carries no tax rate — the
                    // request never captured one for it.
                    $unitCost = $isAsset
                        ? ($assetCostLookup[$line->asset_id][$supplierId] ?? floatval($line->asset?->unit_cost ?? 0))
                        : ($costLookup[$line->ingredient_id][$supplierId] ?? floatval($line->ingredient?->purchase_price ?? 0));

                    $tax = $isAsset ? null : ($taxLookup[$line->ingredient_id] ?? null);
                    $totalCost = round(floatval($line->quantity) * $unitCost, 4);

                    $groups[$supplierId]['lines'][] = [
                        'key'             => ($isAsset ? 'a' . $line->asset_id : $line->ingredient_id) . '-' . $supplierId,
                        'ingredient_id'   => $line->ingredient_id,
                        'asset_id'        => $line->asset_id,
                        'ingredient_name' => $line->displayName(),
                        'quantity'        => floatval($line->quantity),
                        'uom'             => $line->uom?->abbreviation ?? '',
                        'uom_id'          => $line->uom_id,
                        'supplier_id'     => $supplierId,
                        'unit_cost'       => $unitCost,
                        'total_cost'      => $totalCost,
                        'tax_rate_id'     => $tax['id'] ?? null,
                        'tax_label'       => $tax['label'] ?? null,
                        'tax_rate_pct'    => $tax['rate'] ?? 0,
                        'tax_amount'      => $tax ? round($totalCost * ($tax['rate'] / 100), 4) : 0,
                        'source'          => $isAsset ? 'asset' : 'supplier',
                        'excluded'        => false,
                    ];
                }

                $groups[$supplierId]['outlet_ids'][] = $pr->outlet_id;
            }
        }

        // Compute po_total for each group
        foreach ($groups as &$g) {
            $g['outlet_ids'] = array_values(array_unique($g['outlet_ids']));
            $g['po_total'] = collect($g['lines'])->sum('total_cost');
            $g['tax_total'] = collect($g['lines'])->sum('tax_amount');
        }
        unset($g);

        // Supplier options for dropdowns
        $supplierOptions = Supplier::where('is_active', true)->orderBy('name')
            ->get(['id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->toArray();

        // Kitchen options
        $kitchenOptions = \App\Models\CentralKitchen::where('is_active', true)
            ->get(['id', 'name'])->map(fn ($k) => ['id' => $k->id, 'name' => $k->name])->toArray();

        return [
            'groups'             => array_values($groups),
            'cost_lookup'        => $costLookup,
            // Assets price out of a different table, so moving one to another
            // supplier on the preview needs its own lookup or the row keeps
            // the first supplier's price.
            'asset_cost_lookup'  => $assetCostLookup,
            'tax_lookup'         => $taxLookup,
            'supplier_options'   => $supplierOptions,
            'kitchen_options'    => $kitchenOptions,
            // Lines routed to kitchen production instead of supplier POs —
            // surfaced in the preview so their absence is explained.
            'kitchen_line_count' => $kitchenLineCount,
            // Asset lines are consolidated like everything else now. The count
            // stays because they behave differently AFTER the order: they are
            // received into the asset register rather than into stock, and the
            // preview is the last screen before that is decided.
            'asset_line_count'   => $assetLineCount,
        ];
    }

    /**
     * Create POs from a customized/edited preview structure.
     */
    public static function consolidateFromCustomized(array $editableGroups, int $cpuId, array $purchaseRequestIds): array
    {
        return DB::transaction(function () use ($editableGroups, $cpuId, $purchaseRequestIds) {
            $companyId = Auth::user()->company_id;
            $userId    = Auth::id();

            // Kitchen-sourced lines are excluded from the editable preview, so
            // handle them here (same as the non-edit consolidate path) to avoid
            // silently dropping them when the PRs are marked converted below.
            $prs = PurchaseRequest::with('lines.ingredient', 'lines.asset')
                ->whereIn('id', $purchaseRequestIds)
                ->where('status', PurchaseRequest::STATUS_APPROVED)
                ->get();
            $createdPoIds = self::createKitchenProductionOrders($prs, $companyId, $userId);

            foreach ($editableGroups as $group) {
                $supplierId = (int) $group['supplier_id'];
                if ($supplierId === 0) continue;

                $activeLines = collect($group['lines'])->filter(fn ($l) => ! ($l['excluded'] ?? false));
                if ($activeLines->isEmpty()) continue;

                // Merge same ingredients (in case of regrouping)
                // Keyed on what the line points at. Regrouping on
                // ingredient_id alone folded every asset in the group into
                // one line, since they all carry a null one.
                $merged = $activeLines->groupBy(fn ($l) => self::mergeKey($l))->map(function ($items) {
                    $first = $items->first();
                    return [
                        'ingredient_id' => $first['ingredient_id'],
                        'asset_id'      => $first['asset_id'] ?? null,
                        'quantity'      => $items->sum('quantity'),
                        'uom_id'        => $first['uom_id'],
                        'unit_cost'     => floatval($first['unit_cost']),
                        'tax_rate_id'   => $first['tax_rate_id'] ?? null,
                        'tax_rate_pct'  => floatval($first['tax_rate_pct'] ?? 0),
                    ];
                });

                // Generate PO number
                $date = Carbon::now()->format('Ymd');
                $poPrefix = "PO-{$date}-";
                $latestPo = PurchaseOrder::withoutGlobalScopes()
                    ->where('po_number', 'like', "{$poPrefix}%")
                    ->orderByDesc('po_number')
                    ->value('po_number');
                $poSeq = $latestPo ? ((int) substr($latestPo, strrpos($latestPo, '-') + 1) + 1) : 1;
                $poNumber = $poPrefix . str_pad($poSeq, 3, '0', STR_PAD_LEFT);

                $outletIds = array_unique($group['outlet_ids'] ?? []);
                $deliveryOutletId = count($outletIds) === 1 ? $outletIds[0] : null;
                $poOutletId = $outletIds[0] ?? null;

                $subtotal = 0;
                $taxTotal = 0;
                $poLines  = [];

                foreach ($merged as $item) {
                    $totalCost = round($item['quantity'] * $item['unit_cost'], 4);
                    $taxAmount = $item['tax_rate_pct'] > 0 ? round($totalCost * ($item['tax_rate_pct'] / 100), 4) : 0;
                    $subtotal += $totalCost;
                    $taxTotal += $taxAmount;

                    $poLines[] = [
                        'ingredient_id' => $item['ingredient_id'],
                        'asset_id'      => $item['asset_id'],
                        'quantity'      => $item['quantity'],
                        'uom_id'        => $item['uom_id'],
                        'unit_cost'     => $item['unit_cost'],
                        'total_cost'    => $totalCost,
                        'tax_rate_id'   => $item['tax_rate_id'],
                        'tax_amount'    => $taxAmount,
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
                    'total_amount'        => round($subtotal + $taxTotal, 4),
                    'tax_percent'         => 0,
                    'tax_amount'          => $taxTotal,
                    'delivery_charges'    => 0,
                    'created_by'          => $userId,
                    'purchase_request_id' => count($purchaseRequestIds) === 1 ? $purchaseRequestIds[0] : null,
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
}
