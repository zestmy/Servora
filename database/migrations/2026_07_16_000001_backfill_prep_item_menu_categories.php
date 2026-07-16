<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Prep items now use the same menu categories as recipes (recipes.category,
 * named after recipe_categories rows) instead of ingredient_category_id.
 *
 * For every prep recipe that has an ingredient category but no menu category:
 *  1. ensure a recipe_categories row with the same name exists for the company
 *     (mirroring the root/sub hierarchy of the ingredient category), and
 *  2. copy the ingredient category name into recipes.category.
 *
 * recipes.ingredient_category_id is left untouched so LMS grouping and any
 * historical references keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prepRecipes = DB::table('recipes')
            ->where('is_prep', true)
            ->whereNull('category')
            ->whereNotNull('ingredient_category_id')
            ->whereNull('deleted_at')
            ->get(['id', 'company_id', 'ingredient_category_id']);

        if ($prepRecipes->isEmpty()) {
            return;
        }

        $ingredientCategories = DB::table('ingredient_categories')
            ->whereIn('id', $prepRecipes->pluck('ingredient_category_id')->unique())
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        $parents = DB::table('ingredient_categories')
            ->whereIn('id', $ingredientCategories->pluck('parent_id')->filter()->unique())
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('id');

        foreach ($prepRecipes->groupBy('ingredient_category_id') as $catId => $recipes) {
            $ic = $ingredientCategories->get($catId);
            if (! $ic) {
                continue;
            }

            foreach ($recipes->groupBy('company_id') as $companyId => $companyRecipes) {
                $parent = $ic->parent_id ? $parents->get($ic->parent_id) : null;

                $parentRecipeCatId = null;
                if ($parent) {
                    $parentRecipeCatId = $this->ensureRecipeCategory($companyId, $parent->name, null, $parent->sort_order ?? 0);
                }
                $this->ensureRecipeCategory($companyId, $ic->name, $parentRecipeCatId, $ic->sort_order ?? 0);

                DB::table('recipes')
                    ->whereIn('id', $companyRecipes->pluck('id'))
                    ->update(['category' => $ic->name]);
            }
        }
    }

    /**
     * Find (case-insensitively) or create a recipe category for the company;
     * returns its id.
     */
    private function ensureRecipeCategory(int $companyId, string $name, ?int $parentId, int $sortOrder): int
    {
        $existing = DB::table('recipe_categories')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'))
            ->orderBy('id')
            ->first(['id']);

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('recipe_categories')->insertGetId([
            'company_id' => $companyId,
            'parent_id'  => $parentId,
            'name'       => $name,
            'sort_order' => $sortOrder,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Data backfill — not reversible.
    }
};
