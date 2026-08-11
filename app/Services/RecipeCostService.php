<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Facades\Cache;

/**
 * Which recipes are selling above their food cost target.
 *
 * This scan was written inline, twice, in Dashboard.php — once in
 * buildAlerts() for the business and manager dashboards and again, nearly
 * identically, in chefDashboard(). Both copies loaded EVERY active non-prep
 * recipe with `lines.ingredient.baseUom`, `lines.ingredient.uomConversions`
 * and `lines.uom`, hydrated the lot, and reduced it to a single integer.
 *
 * The eager loading was added deliberately and the comment explaining it is
 * correct as far as it goes: without it, the cost accessor's relationLoaded()
 * fast-path misses and each line lazy-loads its own ingredient, an N+1 cascade
 * of thousands of queries. But fixing the query count left the hydration cost
 * untouched, and that is the expensive half — a company with 400 recipes
 * averaging 12 lines materialises some 5,000 models plus their relations to
 * answer "how many are over 35%".
 *
 * It ran on first paint of the most-visited page in the product, and again on
 * every outlet-filter change, because that filter is wire:model.live. On a
 * 1.97 GB box running five php-fpm workers, this was the single most
 * expensive thing a logged-in user could do by accident.
 *
 * Why not SQL: cost per line is not a column. It comes from the
 * cost_per_recipe_uom accessor, which walks the ingredient's base UOM and its
 * conversion table — the chain UomService owns — and that logic is not
 * expressible as a join without duplicating it in two places and letting the
 * two drift. So the scan stays in PHP and stops being run on every page load
 * instead.
 *
 * The count and the list come from one pass. The chef dashboard used to show
 * a bare count with no way to find out which recipes it meant; showing the
 * worst offenders costs nothing extra once the scan has happened anyway.
 */
class RecipeCostService
{
    /**
     * @return array{count: int, top: array<int, array{id: int, name: string, cost_pct: float}>}
     */
    public function overCost(int $companyId, ?float $targetPct = null, int $limit = 5): array
    {
        $target = $targetPct ?? (float) config('costing.target.over');

        return Cache::remember(
            "recipe-overcost:{$companyId}:{$target}:{$limit}",
            (int) config('costing.overcost_cache_ttl'),
            fn () => $this->scan($target, $limit),
        );
    }

    /**
     * Drop the cached scan for a company.
     *
     * Nothing calls this yet. It is here so that when recipe or ingredient
     * costs become a place where staleness is felt — a chef edits a recipe and
     * wants the dashboard to agree with them immediately — the invalidation
     * point already exists and is named, rather than someone reaching for the
     * TTL and shortening it for everybody.
     */
    public function forget(int $companyId, ?float $targetPct = null, int $limit = 5): void
    {
        $target = $targetPct ?? (float) config('costing.target.over');

        Cache::forget("recipe-overcost:{$companyId}:{$target}:{$limit}");
    }

    private function scan(float $target, int $limit): array
    {
        $over = Recipe::query()
            ->where('is_active', true)
            ->where('is_prep', false)
            ->where('selling_price', '>', 0)
            ->with(['lines.ingredient.baseUom', 'lines.ingredient.uomConversions', 'lines.uom'])
            ->get()
            ->map(function ($recipe) {
                $totalCost = $recipe->lines->sum(fn ($line) => $line->cost_per_recipe_uom * $line->quantity);

                // Guard the divisor rather than the result: a recipe saved with
                // no yield would otherwise divide by zero, and a recipe saved
                // with no price is already excluded by the query above.
                $yield       = max((float) $recipe->yield_quantity, 0.0001);
                $costPerUnit = $totalCost / $yield;

                return [
                    'id'       => $recipe->id,
                    'name'     => $recipe->name,
                    'cost_pct' => round(($costPerUnit / (float) $recipe->selling_price) * 100, 1),
                ];
            })
            ->filter(fn ($row) => $row['cost_pct'] > $target)
            ->sortByDesc('cost_pct')
            ->values();

        return [
            'count' => $over->count(),
            'top'   => $over->take($limit)->all(),
        ];
    }
}
