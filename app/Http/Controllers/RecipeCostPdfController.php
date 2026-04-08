<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
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

    public function all()
    {
        $recipes = Recipe::with([
            'lines.ingredient.baseUom', 'lines.ingredient.uomConversions', 'lines.ingredient.taxRate',
            'lines.uom', 'yieldUom', 'ingredientCategory', 'department',
            'prices.priceClass',
        ])->where('is_active', true)->where('is_prep', false)->orderBy('name')->get();

        $recipesData = $recipes->map(fn ($r) => $this->buildRecipeData($r))->values()->all();

        $company = Auth::user()->company;
        $brandName = $company?->brand_name ?: $company?->name;
        $exportedBy = Auth::user()->name;
        $logoBase64 = $this->companyLogoBase64();

        $pdf = Pdf::loadView('pdf.recipe-cost-all', compact('recipesData', 'company', 'brandName', 'exportedBy', 'logoBase64'));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('all-recipe-costs.pdf');
    }

    public function summary()
    {
        $recipes = Recipe::with([
            'lines.ingredient.baseUom', 'lines.ingredient.uomConversions', 'lines.ingredient.taxRate',
            'lines.uom', 'yieldUom',
            'prices.priceClass',
        ])->where('is_active', true)->where('is_prep', false)->orderBy('category')->orderBy('name')->get();

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

            // Legacy fallback
            $row['legacy_price'] = $data['legacyPrice'];
            $row['legacy_food_cost_pct'] = $data['legacyFoodCostPct'];

            return $row;
        })->values()->all();

        $company = Auth::user()->company;
        $brandName = $company?->brand_name ?: $company?->name;
        $exportedBy = Auth::user()->name;
        $logoBase64 = $this->companyLogoBase64();

        $pdf = Pdf::loadView('pdf.recipe-cost-summary', compact('summaryRows', 'priceClasses', 'company', 'brandName', 'exportedBy', 'logoBase64'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('recipe-cost-summary.pdf');
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
