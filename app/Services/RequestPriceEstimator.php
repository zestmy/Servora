<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetSupplier;
use App\Models\Ingredient;
use App\Models\PurchaseRequestLine;
use App\Models\SupplierIngredient;

/**
 * What a requested line is likely to cost.
 *
 * A purchase request carries no price of its own — cost is settled on the
 * purchase order, against a real supplier, and `purchase_request_lines` has
 * no unit_cost column on purpose. This exists so the person approving a
 * request can still see roughly what they are approving.
 *
 * IT IS AN ESTIMATE AND MUST STAY ONE. Nothing here is written back, and the
 * order never reads it: the PO prices itself from the same sources at the
 * moment it is raised. A stored figure would be a second number to disagree
 * with the first, which is how a request gets approved at one price and
 * ordered at another with nobody noticing.
 *
 * The order of preference is the same one the order form uses:
 *   1. what this supplier last charged for this item,
 *   2. what the item's own record says it costs,
 *   3. nothing, for a hand-typed name with no record behind it.
 */
class RequestPriceEstimator
{
    /** One line, from ids. Used by the request form as rows are built. */
    public static function estimate(?int $ingredientId, ?int $assetId, ?int $supplierId): float
    {
        if ($assetId) {
            if ($supplierId) {
                $last = AssetSupplier::where('asset_id', $assetId)
                    ->where('supplier_id', $supplierId)
                    ->value('last_cost');

                if ($last !== null) {
                    return round(floatval($last), 4);
                }
            }

            return round(floatval(Asset::find($assetId)?->unit_cost ?? 0), 4);
        }

        if ($ingredientId) {
            if ($supplierId) {
                $last = SupplierIngredient::where('ingredient_id', $ingredientId)
                    ->where('supplier_id', $supplierId)
                    ->value('last_cost');

                if ($last !== null) {
                    return round(floatval($last), 4);
                }
            }

            return round(floatval(Ingredient::find($ingredientId)?->purchase_price ?? 0), 4);
        }

        // A hand-typed name has nothing behind it to price.
        return 0.0;
    }

    /**
     * Every line of a request at once.
     *
     * The PDF prints one row per line and would otherwise ask the database
     * twice per row; a request loaded from a form template can carry a
     * hundred of them.
     *
     * @param  iterable<PurchaseRequestLine>  $lines
     * @return array<int, float>  line id => unit price
     */
    public static function forLines(iterable $lines): array
    {
        $lines = collect($lines);

        $ingredientKeys = [];
        $assetKeys      = [];

        foreach ($lines as $line) {
            if ($line->asset_id && $line->preferred_supplier_id) {
                $assetKeys[] = [$line->asset_id, $line->preferred_supplier_id];
            } elseif ($line->ingredient_id && $line->preferred_supplier_id) {
                $ingredientKeys[] = [$line->ingredient_id, $line->preferred_supplier_id];
            }
        }

        $supplierCosts = [];
        if ($ingredientKeys !== []) {
            SupplierIngredient::whereIn('ingredient_id', array_column($ingredientKeys, 0))
                ->whereIn('supplier_id', array_column($ingredientKeys, 1))
                ->get(['ingredient_id', 'supplier_id', 'last_cost'])
                ->each(function ($si) use (&$supplierCosts) {
                    $supplierCosts[$si->ingredient_id . ':' . $si->supplier_id] = floatval($si->last_cost);
                });
        }

        $assetCosts = [];
        if ($assetKeys !== []) {
            AssetSupplier::whereIn('asset_id', array_column($assetKeys, 0))
                ->whereIn('supplier_id', array_column($assetKeys, 1))
                ->get(['asset_id', 'supplier_id', 'last_cost'])
                ->each(function ($as) use (&$assetCosts) {
                    $assetCosts[$as->asset_id . ':' . $as->supplier_id] = floatval($as->last_cost);
                });
        }

        $prices = [];

        foreach ($lines as $line) {
            $key = $line->preferred_supplier_id
                ? (($line->asset_id ?: $line->ingredient_id) . ':' . $line->preferred_supplier_id)
                : null;

            if ($line->asset_id) {
                $prices[$line->id] = round(
                    $key !== null && isset($assetCosts[$key])
                        ? $assetCosts[$key]
                        : floatval($line->asset?->unit_cost ?? 0),
                    4
                );
                continue;
            }

            if ($line->ingredient_id) {
                $prices[$line->id] = round(
                    $key !== null && isset($supplierCosts[$key])
                        ? $supplierCosts[$key]
                        : floatval($line->ingredient?->purchase_price ?? 0),
                    4
                );
                continue;
            }

            $prices[$line->id] = 0.0;
        }

        return $prices;
    }
}
