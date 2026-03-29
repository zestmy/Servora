<?php

namespace App\Livewire\Kitchen;

use App\Models\CentralKitchen;
use App\Models\Ingredient;
use App\Models\ProductionRecipe;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ProductionRecipeForm extends Component
{
    public ?int $recipeId = null;

    // Basic
    public string $name        = '';
    public string $code        = '';
    public string $category    = '';
    public ?int   $kitchen_id  = null;
    public string $description = '';

    // Yield
    public string $yield_quantity = '1';
    public ?int   $yield_uom_id  = null;

    // Packaging
    public string $packaging_uom        = '';
    public string $per_carton_qty       = '';
    public string $carton_weight        = '';
    public string $shelf_life_days      = '';
    public string $storage_temperature  = '';

    // Costing inputs
    public string $packaging_cost_per_unit = '0';
    public string $label_cost              = '0';
    public string $selling_price_per_unit  = '0';

    // Calculated (display only)
    public float $raw_material_cost   = 0;
    public float $total_cost_per_unit = 0;
    public float $margin              = 0;
    public float $margin_percent      = 0;

    // Ingredient lines: [{ingredient_id, ingredient_name, quantity, uom_id, uom_name, waste_percentage, unit_cost}]
    public array  $lines            = [];
    public string $ingredientSearch = '';

    protected function rules(): array
    {
        return [
            'name'                     => 'required|string|max:255',
            'code'                     => 'nullable|string|max:100',
            'category'                 => 'nullable|string|max:100',
            'kitchen_id'               => 'required|exists:central_kitchens,id',
            'description'              => 'nullable|string',
            'yield_quantity'           => 'required|numeric|min:0.0001',
            'yield_uom_id'            => 'required|exists:units_of_measure,id',
            'packaging_uom'            => 'nullable|string|max:100',
            'per_carton_qty'           => 'nullable|numeric|min:0',
            'carton_weight'            => 'nullable|numeric|min:0',
            'shelf_life_days'          => 'nullable|integer|min:0',
            'storage_temperature'      => 'nullable|string|max:50',
            'packaging_cost_per_unit'  => 'nullable|numeric|min:0',
            'label_cost'               => 'nullable|numeric|min:0',
            'selling_price_per_unit'   => 'nullable|numeric|min:0',
            'lines'                    => 'required|array|min:1',
            'lines.*.ingredient_id'    => 'required|exists:ingredients,id',
            'lines.*.quantity'         => 'required|numeric|min:0.0001',
            'lines.*.uom_id'          => 'required|exists:units_of_measure,id',
            'lines.*.waste_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'              => 'Recipe name is required.',
            'kitchen_id.required'        => 'Please select a kitchen.',
            'yield_quantity.required'     => 'Yield quantity is required.',
            'yield_uom_id.required'      => 'Yield UOM is required.',
            'lines.required'             => 'Add at least one ingredient line.',
            'lines.min'                  => 'Add at least one ingredient line.',
            'lines.*.quantity.min'       => 'Quantity must be greater than zero.',
        ];
    }

    public function mount(?int $id = null): void
    {
        if (! $id) return;

        $recipe = ProductionRecipe::with(['lines.ingredient', 'lines.uom'])->findOrFail($id);

        $this->recipeId               = $recipe->id;
        $this->name                   = $recipe->name;
        $this->code                   = $recipe->code ?? '';
        $this->category               = $recipe->category ?? '';
        $this->kitchen_id             = $recipe->kitchen_id;
        $this->description            = $recipe->description ?? '';
        $this->yield_quantity         = (string) floatval($recipe->yield_quantity);
        $this->yield_uom_id          = $recipe->yield_uom_id;
        $this->packaging_uom          = $recipe->packaging_uom ?? '';
        $this->per_carton_qty         = $recipe->per_carton_qty ? (string) $recipe->per_carton_qty : '';
        $this->carton_weight          = $recipe->carton_weight ? (string) floatval($recipe->carton_weight) : '';
        $this->shelf_life_days        = $recipe->shelf_life_days ? (string) $recipe->shelf_life_days : '';
        $this->storage_temperature    = $recipe->storage_temperature ?? '';
        $this->packaging_cost_per_unit = (string) floatval($recipe->packaging_cost_per_unit);
        $this->label_cost             = (string) floatval($recipe->label_cost);
        $this->selling_price_per_unit = (string) floatval($recipe->selling_price_per_unit);

        $this->lines = $recipe->lines->map(fn ($l) => [
            'ingredient_id'    => $l->ingredient_id,
            'ingredient_name'  => $l->ingredient?->name ?? '-',
            'quantity'         => (string) floatval($l->quantity),
            'uom_id'           => $l->uom_id,
            'uom_name'         => $l->uom?->abbreviation ?? '-',
            'waste_percentage' => (string) floatval($l->waste_percentage),
            'unit_cost'        => (string) floatval($l->ingredient?->purchase_price ?? 0),
        ])->toArray();

        $this->recalculate();
    }

    // ── Ingredient Search & Add ─────────────────────────────────────────

    public function addIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::with('baseUom')->find($ingredientId);
        if (! $ingredient) return;

        // Skip duplicates
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $this->lines[] = [
            'ingredient_id'    => $ingredientId,
            'ingredient_name'  => $ingredient->name,
            'quantity'         => '1',
            'uom_id'           => $ingredient->base_uom_id,
            'uom_name'         => $ingredient->baseUom?->abbreviation ?? '-',
            'waste_percentage' => '0',
            'unit_cost'        => (string) floatval($ingredient->purchase_price ?? 0),
        ];

        $this->ingredientSearch = '';
        $this->recalculate();
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
        $this->recalculate();
    }

    public function updatedLines(): void
    {
        $this->recalculate();
    }

    public function updatedYieldQuantity(): void
    {
        $this->recalculate();
    }

    public function updatedPackagingCostPerUnit(): void
    {
        $this->recalculate();
    }

    public function updatedLabelCost(): void
    {
        $this->recalculate();
    }

    public function updatedSellingPricePerUnit(): void
    {
        $this->recalculate();
    }

    // ── Costing Calculation ─────────────────────────────────────────────

    private function recalculate(): void
    {
        $rawCost = 0;
        foreach ($this->lines as $line) {
            $cost        = floatval($line['unit_cost'] ?? 0);
            $qty         = floatval($line['quantity'] ?? 0);
            $wasteFactor = 1 + (floatval($line['waste_percentage'] ?? 0) / 100);
            $rawCost    += $cost * $qty * $wasteFactor;
        }

        $yield        = max(floatval($this->yield_quantity), 0.0001);
        $packaging    = floatval($this->packaging_cost_per_unit);
        $label        = floatval($this->label_cost);
        $costPerUnit  = ($rawCost + $packaging + $label) / $yield;
        $selling      = floatval($this->selling_price_per_unit);
        $margin       = $selling - $costPerUnit;
        $marginPct    = $selling > 0 ? ($margin / $selling) * 100 : 0;

        $this->raw_material_cost   = round($rawCost, 4);
        $this->total_cost_per_unit = round($costPerUnit, 4);
        $this->margin              = round($margin, 4);
        $this->margin_percent      = round($marginPct, 2);
    }

    // ── Save ────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate();
        $this->recalculate();

        $user = Auth::user();

        DB::transaction(function () use ($user) {
            $data = [
                'name'                   => $this->name,
                'code'                   => $this->code ?: null,
                'category'               => $this->category ?: null,
                'kitchen_id'             => $this->kitchen_id,
                'description'            => $this->description ?: null,
                'yield_quantity'         => floatval($this->yield_quantity),
                'yield_uom_id'          => $this->yield_uom_id,
                'packaging_uom'          => $this->packaging_uom ?: null,
                'per_carton_qty'         => $this->per_carton_qty ? (int) $this->per_carton_qty : null,
                'carton_weight'          => $this->carton_weight ? floatval($this->carton_weight) : null,
                'shelf_life_days'        => $this->shelf_life_days ? (int) $this->shelf_life_days : null,
                'storage_temperature'    => $this->storage_temperature ?: null,
                'packaging_cost_per_unit' => floatval($this->packaging_cost_per_unit),
                'label_cost'             => floatval($this->label_cost),
                'raw_material_cost'      => $this->raw_material_cost,
                'total_cost_per_unit'    => $this->total_cost_per_unit,
                'selling_price_per_unit' => floatval($this->selling_price_per_unit),
            ];

            if ($this->recipeId) {
                $recipe = ProductionRecipe::findOrFail($this->recipeId);
                $recipe->update($data);
            } else {
                $data['company_id']  = $user->company_id;
                $data['created_by']  = Auth::id();
                $data['is_active']   = true;
                $recipe = ProductionRecipe::create($data);
            }

            // Sync lines
            $recipe->lines()->delete();
            foreach ($this->lines as $idx => $line) {
                $recipe->lines()->create([
                    'ingredient_id'    => $line['ingredient_id'],
                    'quantity'         => floatval($line['quantity']),
                    'uom_id'           => $line['uom_id'],
                    'waste_percentage' => floatval($line['waste_percentage'] ?? 0),
                    'sort_order'       => $idx + 1,
                ]);
            }
        });

        session()->flash('success', $this->recipeId ? 'Production recipe updated.' : 'Production recipe created.');
        $this->redirectRoute('kitchen.recipes.index');
    }

    // ── Render ──────────────────────────────────────────────────────────

    public function render()
    {
        $kitchens = CentralKitchen::active()->orderBy('name')->get();
        $uoms     = UnitOfMeasure::orderBy('name')->get();

        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $searchResults = Ingredient::with('baseUom')
                ->where('is_active', true)
                ->where(fn ($q) => $q
                    ->where('name', 'like', '%' . $this->ingredientSearch . '%')
                    ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%')
                )
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        $pageTitle = $this->recipeId
            ? 'Edit: ' . $this->name
            : 'New Production Recipe';

        return view('livewire.kitchen.production-recipe-form', compact('kitchens', 'uoms', 'searchResults'))
            ->layout('layouts.kitchen', ['title' => $pageTitle]);
    }
}
