<?php

namespace App\Livewire\Recipes;

use App\Models\Recipe;
use App\Models\RecipePriceClass;
use App\Services\UomService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Show extends Component
{
    public int $recipeId;

    public function mount(int $id): void
    {
        $this->recipeId = $id;
    }

    public function render()
    {
        $recipe = Recipe::with([
            'lines.ingredient.baseUom', 'lines.ingredient.uomConversions', 'lines.ingredient.taxRate',
            'lines.uom', 'yieldUom', 'ingredientCategory', 'department',
            'prices.priceClass', 'outlets', 'images',
        ])->findOrFail($this->recipeId);

        $uomService = app(UomService::class);
        $company = Auth::user()?->company;

        // Calculate line costs
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
                    'is_prep'          => (bool) $ingredient->is_prep,
                    'quantity'         => $qty,
                    'uom'              => $uom->abbreviation,
                    'waste_percentage' => floatval($line->waste_percentage),
                    'unit_cost'        => $costPerUom,
                    'line_cost'        => $lineCost,
                    'tax_pct'          => $taxPct,
                    'line_tax'         => $lineTax,
                ];
            }
        }

        // Extra costs
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
                'gross_margin'  => $sp > 0 ? (($sp - $grandCost) / $sp) * 100 : null,
            ];
        }

        // Fallback to legacy selling_price if no price classes
        $legacyPrice = floatval($recipe->selling_price);
        $legacyFoodCostPct = $legacyPrice > 0 ? ($grandCost / $legacyPrice) * 100 : null;

        return view('livewire.recipes.show', compact(
            'recipe', 'lineData', 'totalCost', 'totalTax', 'extraCosts', 'extraCostTotal',
            'grandCost', 'yieldQty', 'costPerServing', 'pricingAnalysis',
            'legacyPrice', 'legacyFoodCostPct'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $recipe->name]);
    }
}
