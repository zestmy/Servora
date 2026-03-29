<?php

namespace App\Livewire\Inventory;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Recipe;
use App\Models\UnitOfMeasure;
use App\Services\UomService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PrepItemForm extends Component
{
    // Linked IDs (null = new)
    public ?int $recipeId     = null;
    public ?int $ingredientId = null;

    // Header
    public string $name                   = '';
    public string $code                   = '';
    public string $notes                  = '';
    public string $yield_quantity         = '1';
    public ?int   $yield_uom_id           = null;
    public bool   $is_active              = true;
    public ?int   $ingredient_category_id = null;

    // Recipe lines: [ingredient_id, ingredient_name, quantity, uom_id, uom_name, waste_percentage]
    public array  $lines            = [];
    public string $ingredientSearch = '';

    protected function rules(): array
    {
        return [
            'name'                     => 'required|string|max:255',
            'code'                     => 'nullable|string|max:50',
            'notes'                    => 'nullable|string',
            'yield_quantity'           => 'required|numeric|min:0.0001',
            'yield_uom_id'             => 'required|exists:units_of_measure,id',
            'lines'                    => 'required|array|min:1',
            'lines.*.ingredient_id'    => 'required|exists:ingredients,id',
            'lines.*.quantity'         => 'required|numeric|min:0.0001',
            'lines.*.uom_id'           => 'required|exists:units_of_measure,id',
            'lines.*.waste_percentage' => 'required|numeric|min:0|max:100',
        ];
    }

    protected function messages(): array
    {
        return [
            'yield_uom_id.required'           => 'Yield UOM is required.',
            'lines.required'                  => 'Add at least one ingredient.',
            'lines.min'                       => 'Add at least one ingredient.',
            'lines.*.quantity.min'            => 'Quantity must be greater than zero.',
            'lines.*.waste_percentage.max'    => 'Waste cannot exceed 100%.',
        ];
    }

    public function mount(?int $id = null): void
    {
        if (! $id) return;

        // $id is the Recipe ID for the prep item
        $recipe = Recipe::with(['lines.ingredient', 'lines.uom', 'ingredient'])->findOrFail($id);

        $this->recipeId               = $recipe->id;
        $this->ingredientId           = $recipe->ingredient?->id;
        $this->name                   = $recipe->name;
        $this->code                   = $recipe->code ?? '';
        $this->notes                  = $recipe->description ?? '';
        $this->yield_quantity         = $this->fmt($recipe->yield_quantity);
        $this->yield_uom_id           = $recipe->yield_uom_id;
        $this->is_active              = $recipe->is_active;
        $this->ingredient_category_id = $recipe->ingredient?->ingredient_category_id
                                        ?? $recipe->ingredient_category_id;

        $this->lines = $recipe->lines->map(fn ($l) => [
            'ingredient_id'    => $l->ingredient_id,
            'ingredient_name'  => $l->ingredient?->name ?? '—',
            'quantity'         => $this->fmt($l->quantity),
            'uom_id'           => $l->uom_id,
            'uom_name'         => $l->uom?->name ?? '',
            'waste_percentage' => $this->fmt($l->waste_percentage, 2),
        ])->toArray();
    }

    // ── Ingredient search ─────────────────────────────────────────────────

    public function addIngredient(int $ingredientId): void
    {
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $ingredient = Ingredient::with(['baseUom', 'recipeUom'])->findOrFail($ingredientId);

        $this->lines[] = [
            'ingredient_id'    => $ingredient->id,
            'ingredient_name'  => $ingredient->name,
            'quantity'         => '1',
            'uom_id'           => $ingredient->recipe_uom_id ?? $ingredient->base_uom_id,
            'uom_name'         => $ingredient->recipeUom?->name ?? $ingredient->baseUom?->name ?? '',
            'waste_percentage' => '0',
        ];

        $this->ingredientSearch = '';
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    // ── Save ─────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate();

        [$lineCosts, $totalCost] = $this->computeLineCosts();
        $yieldQty = max(floatval($this->yield_quantity), 0.0001);
        $costPerYieldUnit = $totalCost / $yieldQty;

        DB::transaction(function () use ($totalCost, $costPerYieldUnit, $yieldQty) {
            $recipeData = [
                'name'                   => $this->name,
                'code'                   => $this->code ?: null,
                'description'            => $this->notes ?: null,
                'yield_quantity'         => $this->yield_quantity,
                'yield_uom_id'           => $this->yield_uom_id,
                'selling_price'          => 0,
                'cost_per_yield_unit'    => round($costPerYieldUnit, 4),
                'is_active'              => $this->is_active,
                'is_prep'                => true,
                'ingredient_category_id' => $this->ingredient_category_id,
            ];

            if ($this->recipeId) {
                $recipe = Recipe::findOrFail($this->recipeId);
                $recipe->update($recipeData);
            } else {
                $recipeData['company_id'] = Auth::user()->company_id;
                $recipe = Recipe::create($recipeData);
                $this->recipeId = $recipe->id;
            }

            // Sync recipe lines
            $recipe->lines()->delete();
            foreach ($this->lines as $idx => $line) {
                $recipe->lines()->create([
                    'ingredient_id'    => $line['ingredient_id'],
                    'quantity'         => $line['quantity'],
                    'uom_id'           => $line['uom_id'],
                    'waste_percentage' => $line['waste_percentage'],
                    'sort_order'       => $idx,
                ]);
            }

            // Sync the corresponding Ingredient record (prep item)
            $ingredientData = [
                'name'                   => $this->name,
                'code'                   => $this->code ?: null,
                'base_uom_id'            => $this->yield_uom_id,   // base UOM = yield UOM
                'recipe_uom_id'          => $this->yield_uom_id,
                'current_cost'           => round($costPerYieldUnit, 4),
                'purchase_price'         => 0,
                'yield_percent'          => 100,
                'is_active'              => $this->is_active,
                'is_prep'                => true,
                'prep_recipe_id'         => $recipe->id,
                'ingredient_category_id' => $this->ingredient_category_id,
            ];

            if ($this->ingredientId) {
                Ingredient::findOrFail($this->ingredientId)->update($ingredientData);
            } else {
                $ingredientData['company_id'] = Auth::user()->company_id;
                $ingredient = Ingredient::create($ingredientData);
                $this->ingredientId = $ingredient->id;
            }
        });

        $flash = $this->recipeId ? 'Prep item updated.' : 'Prep item created.';
        session()->flash('success', $flash);
        $this->redirectRoute('inventory.index');
    }

    public function render()
    {
        $uoms = UnitOfMeasure::orderBy('name')->get();

        $categories = IngredientCategory::roots()->active()->ordered()->get();

        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $existingIds = collect($this->lines)->pluck('ingredient_id')->filter()->toArray();
            // Exclude other prep items from being used as sub-ingredients (avoid circular refs)
            $searchResults = Ingredient::with(['baseUom', 'recipeUom', 'uomConversions'])
                ->where('is_active', true)
                ->where('is_prep', false)
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->when($existingIds, fn ($q) => $q->whereNotIn('id', $existingIds))
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        [$lineCosts, $totalCost] = $this->computeLineCosts();
        $yieldQty        = max(floatval($this->yield_quantity), 0.0001);
        $costPerYieldUnit = $totalCost / $yieldQty;

        $pageTitle = $this->recipeId ? 'Edit: ' . ($this->name ?: 'Prep Item') : 'New Prep Item';

        return view('livewire.inventory.prep-item-form', compact(
            'uoms', 'categories', 'searchResults', 'lineCosts', 'totalCost', 'costPerYieldUnit'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => $pageTitle]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function computeLineCosts(): array
    {
        if (empty($this->lines)) return [[], 0.0];

        $ingredientIds = collect($this->lines)->pluck('ingredient_id')->filter()->unique()->values();
        $uomIds        = collect($this->lines)->pluck('uom_id')->filter()->unique()->values();

        $ingredientsMap = $ingredientIds->isNotEmpty()
            ? Ingredient::with(['baseUom', 'uomConversions'])->whereIn('id', $ingredientIds)->get()->keyBy('id')
            : collect();

        $uomsMap = $uomIds->isNotEmpty()
            ? UnitOfMeasure::whereIn('id', $uomIds)->get()->keyBy('id')
            : collect();

        $uomService = app(UomService::class);
        $lineCosts  = [];
        $totalCost  = 0.0;

        foreach ($this->lines as $line) {
            $ingredient = $ingredientsMap->get($line['ingredient_id'] ?? 0);
            $uom        = $uomsMap->get($line['uom_id'] ?? 0);
            $qty        = floatval($line['quantity'] ?? 0);

            if ($ingredient && $uom && $qty > 0) {
                // Use purchase_price as the base (pre-yield) cost for prep item recipe costing.
                // Temporarily override current_cost for the conversion so UomService scales it correctly.
                $originalCost = $ingredient->current_cost;
                $ingredient->current_cost = $ingredient->purchase_price;
                $costPerUom = $uomService->convertCost($ingredient, $uom);
                $ingredient->current_cost = $originalCost;

                $wasteFactor = 1 + (floatval($line['waste_percentage'] ?? 0) / 100);
                $lineCost    = $costPerUom * $wasteFactor * $qty;
                $lineCosts[] = $lineCost;
                $totalCost  += $lineCost;
            } else {
                $lineCosts[] = null;
            }
        }

        return [$lineCosts, $totalCost];
    }

    private function fmt(mixed $val, int $minDecimals = 0): string
    {
        $float = floatval($val);
        $str   = rtrim(rtrim(number_format($float, 4, '.', ''), '0'), '.');
        if ($minDecimals > 0) {
            $parts = explode('.', $str);
            if (strlen($parts[1] ?? '') < $minDecimals) {
                $str = number_format($float, $minDecimals, '.', '');
            }
        }
        return $str ?: '0';
    }
}
