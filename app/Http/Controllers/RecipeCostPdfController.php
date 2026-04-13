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
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('code', 'like', '%' . $search . '%');
            });
        }

        if ($category !== '') {
            $selectedCat = RecipeCategory::with('children')->find((int) $category);
            if ($selectedCat) {
                $names = collect([$selectedCat->name]);
                if ($selectedCat->children->isNotEmpty()) {
                    $names = $names->merge($selectedCat->children->pluck('name'));
                }
                $query->whereIn('category', $names->toArray());
            }
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } else {
            // Default behavior: only active items in PDFs (matches old behavior)
            if ($status === 'all' && ! $request->has('status')) {
                $query->where('is_active', true);
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
            'lines.uom', 'yieldUom', 'ingredientCategory', 'department',
            'prices.priceClass', 'outlets',
        ])->where('is_prep', $isPrep);

        $this->applyFilters($query, $request, $isPrep);

        // Sort by category, then by name for grouped display
        $recipes = $query->orderByRaw("CASE WHEN category IS NULL OR category = '' THEN 1 ELSE 0 END, category")
            ->orderBy('name')->get();

        $recipes = $this->applyCostFilter($recipes, $request);

        // Group by category for the PDF
        $grouped = $recipes->groupBy(fn ($r) => $r->category ?: 'Uncategorised');

        $groupedData = $grouped->map(function ($items) {
            return $items->map(fn ($r) => $this->buildRecipeData($r))->values()->all();
        })->toArray();

        $company = Auth::user()->company;
        $brandName = $company?->brand_name ?: $company?->name;
        $exportedBy = Auth::user()->name;
        $logoBase64 = $this->companyLogoBase64();
        $pageTitle = "All {$label} Costs";
        $totalRecipes = $recipes->count();
        $activeFilters = $this->describeActiveFilters($request);

        $pdf = Pdf::loadView('pdf.recipe-cost-all', compact(
            'groupedData', 'company', 'brandName', 'exportedBy', 'logoBase64',
            'pageTitle', 'totalRecipes', 'activeFilters'
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
            'lines.uom', 'yieldUom',
            'prices.priceClass', 'outlets',
        ])->where('is_prep', $isPrep);

        $this->applyFilters($query, $request, $isPrep);

        $recipes = $query->orderByRaw("CASE WHEN category IS NULL OR category = '' THEN 1 ELSE 0 END, category")
            ->orderBy('name')->get();

        $recipes = $this->applyCostFilter($recipes, $request);

        $priceClasses = RecipePriceClass::ordered()->get();

        $summaryRows = $recipes->map(function ($recipe) use ($priceClasses) {
            $data = $this->buildRecipeData($recipe);
            $row = [
                'name'            => $recipe->name,
                'code'            => $recipe->code,
                'category'        => $recipe->category,
                'yield'           => rtrim(rtrim(number_format($data['yieldQty'], 2), '0'), '.') . ' ' . ($recipe->yieldUom?->abbreviation ?? ''),
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
        $activeFilters = $this->describeActiveFilters($request);

        $pdf = Pdf::loadView('pdf.recipe-cost-summary', compact(
            'summaryRows', 'priceClasses', 'company', 'brandName', 'exportedBy',
            'logoBase64', 'pageTitle', 'totalRecipes', 'activeFilters'
        ));
        $pdf->setPaper('a4', 'landscape');

        $filename = $isPrep ? 'prep-item-cost-summary.pdf' : 'recipe-cost-summary.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Build a human-readable summary of active filters for display in PDF header.
     */
    private function describeActiveFilters(Request $request): array
    {
        $filters = [];

        if ($search = trim((string) $request->get('search', ''))) {
            $filters[] = 'Search: "' . $search . '"';
        }
        if ($categoryId = (int) $request->get('category', 0)) {
            $cat = RecipeCategory::find($categoryId);
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
        $totalCost = 0;
        $totalTax = 0;

        foreach ($recipe->lines as $line) {
            $ingredient = $line->ingredient;
            $uom = $line->uom;
            $qty = floatval($line->quantity);

            if ($ingredient && $uom && $qty > 0) {
                $costPerUom = $uomService->convertCost($ingredient, $uom);
                $wasteFactor = 1 + (floatval($line->waste_percentage) / 100);
                $lineCost = $costPerUom * $wasteFactor * $qty;
                $totalCost += $lineCost;

                $taxRate = $ingredient->effectiveTaxRate($company);
                $taxPct = $taxRate ? floatval($taxRate->rate) : 0;
                $lineTax = $lineCost * ($taxPct / 100);
                $totalTax += $lineTax;

                $lineData[] = [
                    'ingredient'       => $ingredient->name,
                    'quantity'         => $qty,
                    'uom'              => $uom->abbreviation,
                    'waste_percentage' => floatval($line->waste_percentage),
                    'unit_cost'        => $costPerUom,
                    'line_cost'        => $lineCost,
                ];
            }
        }

        $extraCosts = is_array($recipe->extra_costs) ? $recipe->extra_costs : [];
        $extraCostTotal = collect($extraCosts)->sum(function ($c) use ($totalCost) {
            if (($c['type'] ?? 'value') === 'percent') {
                return $totalCost * (floatval($c['amount'] ?? 0) / 100);
            }
            return floatval($c['amount'] ?? 0);
        });
        $grandCost = $totalCost + $extraCostTotal;

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
            'recipe', 'lineData', 'totalCost', 'totalTax', 'extraCosts', 'extraCostTotal',
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
