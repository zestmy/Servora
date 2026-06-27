<?php

namespace App\Services;

use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges duplicate products into a single canonical product.
 *
 * Every table that references the duplicate by `ingredient_id` is repointed to the
 * canonical product inside one transaction, then the duplicates are soft-deleted.
 * This is what actually fixes the recipe-costing drift caused by duplicates: once
 * merged, all recipe lines reference the same product, so price updates flow through.
 *
 * The table list is explicit (not schema-discovered) so the data migration is
 * reviewable; each table is still guarded with Schema checks so a missing/renamed
 * table never aborts a merge.
 */
class ProductMergeService
{
    /**
     * Tables with an `ingredient_id` column and NO unique constraint involving it —
     * safe to bulk-repoint. NULL ingredient_id rows (custom line items) are skipped
     * naturally by the whereIn filter.
     */
    private const SIMPLE_TABLES = [
        'recipe_lines',
        'production_recipe_lines',
        'purchase_order_lines',
        'purchase_request_lines',
        'delivery_order_lines',
        'purchase_record_lines',
        'goods_received_note_lines',
        'procurement_invoice_lines',
        'wastage_record_lines',
        'staff_meal_record_lines',
        'outlet_transfer_lines',
        'stock_transfer_order_lines',
        'stock_take_lines',
        'outlet_prep_request_lines',
        'credit_note_lines',
        'form_template_lines',
        'supplier_price_alerts',
        'price_change_notifications',
        'supplier_item_aliases',
        'ingredient_price_history',
    ];

    /**
     * Merge one or more duplicate products into a canonical product.
     *
     * @param  int          $keepId    The product to keep.
     * @param  array<int>   $mergeIds  The duplicate products to fold in and remove.
     * @return array{keep_id:int, merged_ids:array<int>, moved:array<string,int>}
     */
    public function merge(int $keepId, array $mergeIds): array
    {
        $keep = Ingredient::findOrFail($keepId);

        $mergeIds = collect($mergeIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === (int) $keepId)
            ->unique()
            ->values()
            ->all();

        if (empty($mergeIds)) {
            return ['keep_id' => (int) $keepId, 'merged_ids' => [], 'moved' => []];
        }

        // Guard: never merge across tenants.
        $dupes = Ingredient::whereIn('id', $mergeIds)->get();
        if ($dupes->count() !== count($mergeIds)) {
            throw new \RuntimeException('One or more products to merge no longer exist.');
        }
        foreach ($dupes as $d) {
            if ((int) $d->company_id !== (int) $keep->company_id) {
                throw new \RuntimeException('Cannot merge products from a different company.');
            }
        }

        $moved = [];

        DB::transaction(function () use ($keep, $mergeIds, &$moved) {
            $keepId = (int) $keep->id;

            foreach (self::SIMPLE_TABLES as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'ingredient_id')) {
                    continue;
                }
                $n = DB::table($table)->whereIn('ingredient_id', $mergeIds)->update(['ingredient_id' => $keepId]);
                if ($n) {
                    $moved[$table] = $n;
                }
            }

            // Unique-constrained tables: repoint where there is no collision with an
            // existing canonical row, drop the colliding duplicate rows.
            $this->mergeUniqueWithId('supplier_ingredients', ['supplier_id'], $keepId, $mergeIds, $moved);
            $this->mergeUniqueWithId('ingredient_par_levels', ['outlet_id'], $keepId, $mergeIds, $moved);
            $this->mergeUniqueWithId('supplier_product_mappings', ['company_id', 'supplier_product_id'], $keepId, $mergeIds, $moved);

            // Kitchen inventory: sum on-hand quantity into the canonical row on collision.
            $this->mergeKitchenInventory($keepId, $mergeIds, $moved);

            // Outlet visibility: keep the canonical product's visibility as-is and just
            // drop the duplicates' rows. Transferring a duplicate's outlet restriction
            // could wrongly narrow the kept product (no rows = visible everywhere).
            if (Schema::hasTable('outlet_ingredient')) {
                $n = DB::table('outlet_ingredient')->whereIn('ingredient_id', $mergeIds)->delete();
                if ($n) {
                    $moved['outlet_ingredient_removed'] = $n;
                }
            }

            // UOM conversions belong to a product's own pricing setup; the canonical
            // keeps its own, the duplicates' are discarded.
            if (Schema::hasTable('ingredient_uom_conversions')) {
                $n = DB::table('ingredient_uom_conversions')->whereIn('ingredient_id', $mergeIds)->delete();
                if ($n) {
                    $moved['ingredient_uom_conversions_removed'] = $n;
                }
            }

            Ingredient::whereIn('id', $mergeIds)->delete();
            $moved['products_merged'] = count($mergeIds);
        });

        return ['keep_id' => (int) $keep->id, 'merged_ids' => $mergeIds, 'moved' => $moved];
    }

    /**
     * Repoint rows of a table whose unique key is (…$otherKeys, ingredient_id).
     * Rows whose (…$otherKeys) combo already exists on the canonical product are
     * dropped instead of repointed, to avoid a unique-constraint violation.
     *
     * @param  array<int,string>        $otherKeys
     * @param  array<int>               $mergeIds
     * @param  array<string,int>        $moved
     */
    private function mergeUniqueWithId(string $table, array $otherKeys, int $keepId, array $mergeIds, array &$moved): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'ingredient_id') || ! Schema::hasColumn($table, 'id')) {
            return;
        }

        $taken = [];
        DB::table($table)->where('ingredient_id', $keepId)->get($otherKeys)->each(function ($row) use (&$taken, $otherKeys) {
            $taken[$this->comboKey($row, $otherKeys)] = true;
        });

        $repoint = [];
        $drop = [];
        DB::table($table)
            ->whereIn('ingredient_id', $mergeIds)
            ->orderBy('id')
            ->get(array_merge(['id'], $otherKeys))
            ->each(function ($row) use (&$taken, &$repoint, &$drop, $otherKeys) {
                $key = $this->comboKey($row, $otherKeys);
                if (isset($taken[$key])) {
                    $drop[] = $row->id;
                } else {
                    $taken[$key] = true;
                    $repoint[] = $row->id;
                }
            });

        if ($repoint) {
            DB::table($table)->whereIn('id', $repoint)->update(['ingredient_id' => $keepId]);
            $moved[$table] = count($repoint);
        }
        if ($drop) {
            DB::table($table)->whereIn('id', $drop)->delete();
            $moved[$table . '_removed'] = count($drop);
        }
    }

    /**
     * @param  array<int>          $mergeIds
     * @param  array<string,int>   $moved
     */
    private function mergeKitchenInventory(int $keepId, array $mergeIds, array &$moved): void
    {
        $table = 'kitchen_inventory';
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'ingredient_id')) {
            return;
        }

        $keepByKitchen = DB::table($table)->where('ingredient_id', $keepId)->get()->keyBy('kitchen_id');
        $repoint = 0;
        $combined = 0;

        DB::table($table)->whereIn('ingredient_id', $mergeIds)->orderBy('id')->get()
            ->each(function ($row) use (&$keepByKitchen, $keepId, &$repoint, &$combined, $table) {
                if (isset($keepByKitchen[$row->kitchen_id])) {
                    $target = $keepByKitchen[$row->kitchen_id];
                    DB::table($table)->where('id', $target->id)
                        ->update(['quantity_on_hand' => DB::raw('quantity_on_hand + ' . (float) $row->quantity_on_hand)]);
                    DB::table($table)->where('id', $row->id)->delete();
                    $combined++;
                } else {
                    DB::table($table)->where('id', $row->id)->update(['ingredient_id' => $keepId]);
                    $keepByKitchen[$row->kitchen_id] = (object) ['id' => $row->id, 'kitchen_id' => $row->kitchen_id];
                    $repoint++;
                }
            });

        if ($repoint) {
            $moved[$table] = $repoint;
        }
        if ($combined) {
            $moved[$table . '_combined'] = $combined;
        }
    }

    /**
     * @param  object              $row
     * @param  array<int,string>   $keys
     */
    private function comboKey($row, array $keys): string
    {
        $parts = [];
        foreach ($keys as $k) {
            $parts[] = (string) ($row->$k ?? '');
        }
        return implode('|', $parts);
    }
}
