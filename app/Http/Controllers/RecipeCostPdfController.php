<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipePriceClass;
use App\Services\UomService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecipeCostPdfController extends Controller
{
    public function single(int $id)
    {
        $recipe = Recipe::with([
            'lines.ingredient.baseUom', 'lines.ingredient.uomConversions', 'lines.ingredient.taxRate',
            'lines.uom', 'yieldUom', 'ingredientCategory', 'department',
            'prices.priceClass',
        ])->findOrFail($id);

        $data = $this->buildRecipeData($recipe);
        $data['company'] = Auth::user()->company;
        $data['brandName'] = Auth::user()->company?->brand_name ?: Auth::user()->company?->name;
        $data['exportedBy'] = Auth::user()->name;
        $data['logoBase64'] = $this->companyLogoBase64();

        $pdf = Pdf::loadView('pdf.recipe-cost-single', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("recipe-cost-{$recipe->code}-{$recipe->id}.pdf");
    }

    public function all(Request $request)
    {
        return $this->generateAllPdf($request, isPrep: false);
    }

    public function summary(Request $request)
    {
        return $this->generateSummaryPdf($request, isPrep: false);
    }

    public function prepAll(Request $request)
    {
        return $this->generateAllPdf($request, isPrep: true);
    }

    public function prepSummary(Request $request)
    {
        return $this->generateSummaryPdf($request, isPrep: true);
    }

    /**
     * Apply UI filters (search, category, status, outlet, cost) to a recipe query.
     * Mirrors the logic in App\Livewire\Recipes\Index@render.
     */
    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, Request $request, bool $isPrep): \Illuminate\Database\Eloquent\Builder
    {
        $search   = trim((string) $request->get('search', ''));
        $category = trim((string) $request->get('category', ''));
        $status   = (string) $request->get('status', 'all');
        $outletId = (int)    $request->get('outlet', 0);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('recipes.name', 'like', '%' . $search . '%')
                  ->orWhere('recipes.code', 'like', '%' . $search . '%');
            });
        }

        if ($category !== '') {
            if ($isPrep) {
                $selectedCat = \App\Models\IngredientCategory::with('children')->find((int) $category);
                if ($selectedCat) {
                    $ids = collect([$selectedCat->id]);
                    if ($selectedCat->children->isNotEmpty()) {
                        $ids = $ids->merge($selectedCat->children->pluck('id'));
                    }
                    $query->whereIn('recipes.ingredient_category_id', $ids->toArray());
                }
            } else {
                $selectedCat = RecipeCategory::with('children')->find((int) $category);
                if ($selectedCat) {
                    $names = collect([$selectedCat->name]);
                    if ($selectedCat->children->isNotEmpty()) {
                        $names = $names->merge($selectedCat->children->pluck('name'));
                    }
                    $query->whereIn('recipes.category', $names->toArray());
                }
            }
        }

        if ($status === 'active') {
            $query->where('recipes.is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('recipes.is_active', false);
        } else {
            // Default behavior: only active items in PDFs (matches old behavior)
            if ($status === 'all' && ! $request->has('status')) {
                $query->where('recipes.is_active', true);
            }
        }

        if ($outletId > 0) {
            $query->where(function ($q) use ($outletId) {
                $q->whereHas('outlets', fn ($sub) => $sub->where('outlets.id', $outletId))
                  ->orWhereDoesntHave('outlets');
            });
        }

        return $query;
    }

    /**
     * Match Recipes Index sort: category hierarchy (root, then sub) →
     * manual menu_sort_order → recipe name. Recipes whose category string
     * doesn't match any recipe_category land last.
     */
    private function applyDashboardSort(\Illuminate\Database\Eloquent\Builder $query, bool $isPrep = false): void
    {
        if ($isPrep) {
            // Prep items use ingredient_category_id (FK).
            $query->leftJoin('ingredient_categories as rc', function ($join) {
                    $join->on('rc.id', '=', 'recipes.ingredient_category_id')
                         ->whereNull('rc.deleted_at');
                })
                ->leftJoin('ingredient_categories as rcp', 'rcp.id', '=', 'rc.parent_id');
        } else {
            // Recipes use category string joined by name to recipe_categories.
            $query->leftJoin('recipe_categories as rc', function ($join) {
                    $join->on('rc.name', '=', 'recipes.category')
                         ->on('rc.company_id', '=', 'recipes.company_id')
                         ->whereNull('rc.deleted_at');
                })
                ->leftJoin('recipe_categories as rcp', 'rcp.id', '=', 'rc.parent_id');
        }

        $query->select('recipes.*')
            ->orderByRaw('COALESCE(rcp.sort_order, rc.sort_order) IS NULL')
            ->orderByRaw('COALESCE(rcp.sort_order, rc.sort_order) ASC')
            ->orderByRaw('COALESCE(rcp.name, rc.name) ASC')
            ->orderBy('rc.sort_order')
            ->orderBy('rc.name')
            ->orderBy('recipes.menu_sort_order')
            ->orderBy('recipes.name');
    }

    /**
     * Apply the costFilter (under25/25to35/35to45/over45/none) post-query.
     */
    private function applyCostFilter($recipes, Request $request)
    {
        $costFilter = (string) $request->get('cost', '');
        if ($costFilter === '') return $recipes;

        return $recipes->filter(function ($recipe) use ($costFilter) {
            $totalCost = $recipe->total_cost;
            $selling   = method_exists($recipe, 'getEffectiveSellingPriceAttribute')
                ? $recipe->effective_selling_price
                : floatval($recipe->selling_price);
            $pct = $selling > 0 ? ($totalCost / $selling) * 100 : null;

            return match ($costFilter) {
                'under25' => $pct !== null && $pct <= 25,
                '25to35'  => $pct !== null && $pct > 25 && $pct <= 35,
                '35to45'  => $pct !== null && $pct > 35 && $pct <= 45,
                'over45'  => $pct !== null && $pct > 45,
                'none'    => $pct === null,
                default   => true,
            };
        })->values();
    }

    private function generateAllPdf(Request $request, bool $isPrep)
    {
        $label = $isPrep ? 'Prep Item' : 'Recipe';

        $query = Recipe::with([
            'lines.ingredient.baseUom', 'lines.ingredient.uomConversions', 'lines.ingredient.taxRate',
            'lines.uom', 'yieldUom', 'ingredientCategory.parent', 'department',
            'prices.priceClass', 'outlets',
        ])->where('recipes.is_prep', $isPrep);

        $this->applyFilters($query, $request, $isPrep);
        $this->applyDashboardSort($query, $isPrep);

        $recipes = $query->get();

        $recipes = $this->applyCostFilter($recipes, $request);

        // Group by category for the PDF (Laravel Collections preserve insertion order).
        // Prep items group by their ingredient category root; recipes group by menu category.
        $grouped = $recipes->groupBy(function ($r) use ($isPrep) {
            if ($isPrep) {
                $ic = $r->ingredientCategory;
                $root = $ic?->parent ?? $ic;
                return $root?->name ?: 'Uncategorised';
            }
            return $r->category ?: 'Uncategorised';
        });

        $groupedData = $grouped->map(function ($items) {
            return $items->map(fn ($r) => $this->buildRecipeData($r))->values()->all();
        })->toArray();

        $company = Auth::user()->company;
        $brandName = $company?->brand_name ?: $company?->name;
        $exportedBy = Auth::user()->name;
        $logoBase64 = $this->companyLogoBase64();
        $pageTitle = "All {$label} Costs";
        $totalRecipes = $recipes->count();
        $activeFilters = $this->describeActiveFilters($request, $isPrep);

        $pdf = Pdf::loadView('pdf.recipe-cost-all', compact(
            'groupedData', 'company', 'brandName', 'exportedBy', 'logoBase64',
            'pageTitle', 'totalRecipes', 'activeFilters', 'isPrep'
        ));
        $pdf->setPaper('a4', 'portrait');

        $filename = $isPrep ? 'all-prep-item-costs.pdf' : 'all-recipe-costs.pdf';
        return $pdf->stream($filename);
    }

    private function generateSummaryPdf(Request $request, bool $isPrep)
    {
        $label = $isPrep ? 'Prep Item' : 'Recipe';

        $query = Recipe::with([
            'lines.ingredient.baseUom', 'lines.ingredient.uomConversions', 'lines.ingredient.taxRate',
            'lines.uom', 'yieldUom', 'ingredientCategory.parent',
            'prices.priceClass', 'outlets',
        ])->where('recipes.is_prep', $isPrep);

        $this->applyFilters($query, $request, $isPrep);
        $this->applyDashboardSort($query, $isPrep);

        $recipes = $query->get();

        $recipes = $this->applyCostFilter($recipes, $request);

        $priceClasses = RecipePriceClass::ordered()->get();

        $summaryRows = $recipes->map(function ($recipe) use ($priceClasses, $isPrep) {
            $data = $this->buildRecipeData($recipe);
            // For prep items the "category" column shows the ingredient category
            // root so the group headers match the hierarchical sort order.
            if ($isPrep) {
                $ic = $recipe->ingredientCategory;
                $root = $ic?->parent ?? $ic;
                $categoryLabel = $root?->name ?? '';
            } else {
                $categoryLabel = $recipe->category;
            }
            $row = [
                'name'            => $recipe->name,
                'code'            => $recipe->code,
                'category'        => $categoryLabel,
                'yield'           => rtrim(rtrim(number_format($data['yieldQty'], 2), '0'), '.') . ' ' . ($recipe->yieldUom?->abbreviation ?? ''),
                'ingredient_cost' => $data['totalCost'],
                'packaging_cost'  => $data['packagingCost'],
                'tax'             => $data['totalTaxAll'],
                'total_cost'      => $data['grandCost'],
                'cost_per_serving' => $data['costPerServing'],
                'class_prices'    => [],
            ];

            foreach ($priceClasses as $pc) {
                $pa = collect($data['pricingAnalysis'])->firstWhere('name', $pc->name);
                $row['class_prices'][$pc->id] = $pa ?? ['selling_price' => 0, 'food_cost_pct' => null];
            }

            $row['legacy_price'] = $data['legacyPrice'];
            $row['legacy_food_cost_pct'] = $data['legacyFoodCostPct'];

            return $row;
        })->values()->all();

        $company = Auth::user()->company;
        $brandName = $company?->brand_name ?: $company?->name;
        $exportedBy = Auth::user()->name;
        $logoBase64 = $this->companyLogoBase64();
        $pageTitle = "{$label} Cost Summary";
        $totalRecipes = $recipes->count();
        $activeFilters = $this->describeActiveFilters($request, $isPrep);

        $pdf = Pdf::loadView('pdf.recipe-cost-summary', compact(
            'summaryRows', 'priceClasses', 'company', 'brandName', 'exportedBy',
            'logoBase64', 'pageTitle', 'totalRecipes', 'activeFilters', 'isPrep'
        ));
        $pdf->setPaper('a4', 'landscape');

        $filename = $isPrep ? 'prep-item-cost-summary.pdf' : 'recipe-cost-summary.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Build a human-readable summary of active filters for display in PDF header.
     */
    private function describeActiveFilters(Request $request, bool $isPrep = false): array
    {
        $filters = [];

        if ($search = trim((string) $request->get('search', ''))) {
            $filters[] = 'Search: "' . $search . '"';
        }
        if ($categoryId = (int) $request->get('category', 0)) {
            $cat = $isPrep
                ? \App\Models\IngredientCategory::find($categoryId)
                : RecipeCategory::find($categoryId);
            if ($cat) $filters[] = 'Category: ' . $cat->name;
        }
        if ($status = $request->get('status')) {
            if ($status === 'active') $filters[] = 'Status: Active only';
            elseif ($status === 'inactive') $filters[] = 'Status: Inactive only';
        }
        if ($outletId = (int) $request->get('outlet', 0)) {
            $outlet = \App\Models\Outlet::find($outletId);
            if ($outlet) $filters[] = 'Outlet: ' . $outlet->name;
        }
        if ($costFilter = $request->get('cost')) {
            $costLabels = [
                'under25' => 'Cost % under 25',
                '25to35' => 'Cost % 25–35',
                '35to45' => 'Cost % 35–45',
                'over45' => 'Cost % over 45',
                'none'   => 'No price set',
            ];
            if (isset($costLabels[$costFilter])) $filters[] = $costLabels[$costFilter];
        }

        return $filters;
    }

    private function buildRecipeData(Recipe $recipe): array
    {
        $uomService = app(UomService::class);
        $company = Auth::user()?->company;

        $lineData = [];
        $packagingData = [];
        $totalCost = 0;
        $packagingCost = 0;
        $totalTax = 0;
        $packagingTax = 0;

        foreach ($recipe->lines as $line) {
            $ingredient = $line->ingredient;
            $uom = $line->uom;
            $qty = floatval($line->quantity);

            if ($ingredient && $uom && $qty > 0) {
                $costPerUom = $uomService->convertCost($ingredient, $uom);
                $wasteFactor = 1 + (floatval($line->waste_percentage) / 100);
                $lineCost = $costPerUom * $wasteFactor * $qty;

                $taxRate = $ingredient->effectiveTaxRate($company);
                $taxPct = $taxRate ? floatval($taxRate->rate) : 0;
                $lineTax = $lineCost * ($taxPct / 100);

                $row = [
                    'ingredient'       => $ingredient->name,
                    'quantity'         => $qty,
                    'uom'              => $uom->abbreviation,
                    'waste_percentage' => floatval($line->waste_percentage),
                    'unit_cost'        => $costPerUom,
                    'line_cost'        => $lineCost,
                ];

                if ($line->is_packaging) {
                    $packagingCost += $lineCost;
                    $packagingTax  += $lineTax;
                    $packagingData[] = $row;
                } else {
                    $totalCost += $lineCost;
                    $totalTax  += $lineTax;
                    $lineData[] = $row;
                }
            }
        }

        $extraCosts = is_array($recipe->extra_costs) ? $recipe->extra_costs : [];
        $extraCostTotal = collect($extraCosts)->sum(function ($c) use ($totalCost) {
            if (($c['type'] ?? 'value') === 'percent') {
                return $totalCost * (floatval($c['amount'] ?? 0) / 100);
            }
            return floatval($c['amount'] ?? 0);
        });
        $totalTaxAll = $totalTax + $packagingTax;
        $grandCost = $totalCost + $packagingCost + $extraCostTotal;

        $yieldQty = max(floatval($recipe->yield_quantity), 0.0001);
        $costPerServing = $grandCost / $yieldQty;

        // Price class analysis
        $priceClasses = RecipePriceClass::ordered()->get();
        $priceMap = $recipe->prices->keyBy('recipe_price_class_id');

        $pricingAnalysis = [];
        foreach ($priceClasses as $pc) {
            $rp = $priceMap->get($pc->id);
            $sp = $rp ? floatval($rp->selling_price) : 0;
            $pricingAnalysis[] = [
                'name'          => $pc->name,
                'is_default'    => $pc->is_default,
                'selling_price' => $sp,
                'food_cost_pct' => $sp > 0 ? ($grandCost / $sp) * 100 : null,
                'gross_profit'  => $sp > 0 ? $sp - $grandCost : null,
            ];
        }

        $legacyPrice = floatval($recipe->selling_price);
        $legacyFoodCostPct = $legacyPrice > 0 ? ($grandCost / $legacyPrice) * 100 : null;

        return compact(
            'recipe', 'lineData', 'packagingData', 'totalCost', 'packagingCost',
            'totalTax', 'packagingTax', 'totalTaxAll',
            'extraCosts', 'extraCostTotal',
            'grandCost', 'yieldQty', 'costPerServing', 'pricingAnalysis',
            'legacyPrice', 'legacyFoodCostPct'
        );
    }

    private function companyLogoBase64(): ?string
    {
        $company = Auth::user()->company;
        if (! $company?->logo_path) return null;

        $path = storage_path('app/public/' . $company->logo_path);
        if (! file_exists($path)) return null;

        $mime = mime_content_type($path);
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
}
