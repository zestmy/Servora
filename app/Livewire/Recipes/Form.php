<?php

namespace App\Livewire\Recipes;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeImage;
use App\Models\UnitOfMeasure;
use App\Services\UomService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;
    public ?int $recipeId = null;

    // Header fields
    public string $name = '';
    public string $code = '';
    public string $description = '';
    public string $yield_quantity = '1';
    public ?int   $yield_uom_id = null;
    public string $selling_price = '0';
    public string $category = '';
    public ?int   $ingredient_category_id = null;
    public bool   $is_active = true;

    // Ingredient lines: each row = [ingredient_id, ingredient_name, quantity, uom_id, waste_percentage]
    public array $lines = [];

    // Images (dine-in & takeaway)
    public array $newDineInImages = [];
    public array $newTakeawayImages = [];
    public array $existingDineInImages = [];
    public array $existingTakeawayImages = [];

    // Ingredient search
    public string $ingredientSearch = '';

    protected function rules(): array
    {
        return [
            'name'                       => 'required|string|max:255',
            'code'                       => 'nullable|string|max:50',
            'description'                => 'nullable|string',
            'yield_quantity'             => 'required|numeric|min:0.0001',
            'yield_uom_id'               => 'required|exists:units_of_measure,id',
            'selling_price'              => 'required|numeric|min:0',
            'category'                   => 'nullable|string|max:100',
            'ingredient_category_id'     => 'nullable|exists:ingredient_categories,id',
            'lines.*.ingredient_id'      => 'required|exists:ingredients,id',
            'lines.*.quantity'           => 'required|numeric|min:0.0001',
            'lines.*.uom_id'             => 'required|exists:units_of_measure,id',
            'lines.*.waste_percentage'   => 'required|numeric|min:0|max:100',
            'newDineInImages.*'          => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'newTakeawayImages.*'        => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ];
    }

    protected function messages(): array
    {
        return [
            'yield_uom_id.required'              => 'Yield UOM is required.',
            'lines.*.ingredient_id.required'     => 'Ingredient is required.',
            'lines.*.quantity.required'          => 'Quantity is required.',
            'lines.*.quantity.min'               => 'Quantity must be greater than zero.',
            'lines.*.uom_id.required'            => 'UOM is required.',
            'lines.*.waste_percentage.max'       => 'Waste cannot exceed 100%.',
        ];
    }

    public function mount(?int $id = null): void
    {
        if (! $id) return;

        $recipe = Recipe::with(['lines.ingredient', 'lines.uom', 'images'])->findOrFail($id);

        $this->recipeId               = $recipe->id;
        $this->name                   = $recipe->name;
        $this->code                   = $recipe->code ?? '';
        $this->description            = $recipe->description ?? '';
        $this->yield_quantity         = $this->fmt($recipe->yield_quantity);
        $this->yield_uom_id           = $recipe->yield_uom_id;
        $this->selling_price          = $this->fmt($recipe->selling_price);
        $this->category               = $recipe->category ?? '';
        $this->ingredient_category_id = $recipe->ingredient_category_id;
        $this->is_active              = $recipe->is_active;

        $this->lines = $recipe->lines->map(fn ($l) => [
            'ingredient_id'    => $l->ingredient_id,
            'ingredient_name'  => $l->ingredient?->name ?? '—',
            'is_prep'          => (bool) ($l->ingredient?->is_prep ?? false),
            'quantity'         => $this->fmt($l->quantity),
            'uom_id'           => $l->uom_id,
            'waste_percentage' => $this->fmt($l->waste_percentage, 2),
        ])->toArray();

        $this->existingDineInImages = $recipe->images->where('type', 'dine_in')->values()->map(fn ($img) => [
            'id'        => $img->id,
            'file_name' => $img->file_name,
            'url'       => $img->url(),
            'size'      => $img->humanSize(),
        ])->toArray();

        $this->existingTakeawayImages = $recipe->images->where('type', 'takeaway')->values()->map(fn ($img) => [
            'id'        => $img->id,
            'file_name' => $img->file_name,
            'url'       => $img->url(),
            'size'      => $img->humanSize(),
        ])->toArray();
    }

    public function addIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::find($ingredientId);
        if (! $ingredient) return;

        // Skip duplicate
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $this->lines[] = [
            'ingredient_id'    => $ingredientId,
            'ingredient_name'  => $ingredient->name,
            'is_prep'          => (bool) $ingredient->is_prep,
            'quantity'         => '1',
            'uom_id'           => $ingredient->recipe_uom_id,
            'waste_percentage' => '0',
        ];

        $this->ingredientSearch = '';
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    public function removeExistingImage(int $id): void
    {
        $image = RecipeImage::find($id);
        if ($image) {
            $type = $image->type;
            Storage::disk('public')->delete($image->file_path);
            $image->delete();

            if ($type === 'takeaway') {
                $this->existingTakeawayImages = array_values(
                    array_filter($this->existingTakeawayImages, fn ($img) => $img['id'] !== $id)
                );
            } else {
                $this->existingDineInImages = array_values(
                    array_filter($this->existingDineInImages, fn ($img) => $img['id'] !== $id)
                );
            }
        }
    }

    public function removeNewDineInImage(int $idx): void
    {
        unset($this->newDineInImages[$idx]);
        $this->newDineInImages = array_values($this->newDineInImages);
    }

    public function removeNewTakeawayImage(int $idx): void
    {
        unset($this->newTakeawayImages[$idx]);
        $this->newTakeawayImages = array_values($this->newTakeawayImages);
    }

    public function save(): void
    {
        $this->validate();

        [$lineCosts, $totalCost] = $this->computeLineCosts();

        $yieldQty        = max(floatval($this->yield_quantity), 0.0001);
        $costPerYieldUnit = round($totalCost / $yieldQty, 4);

        $data = [
            'name'                   => $this->name,
            'code'                   => $this->code ?: null,
            'description'            => $this->description ?: null,
            'yield_quantity'         => $this->yield_quantity,
            'yield_uom_id'           => $this->yield_uom_id,
            'selling_price'          => $this->selling_price,
            'cost_per_yield_unit'    => $costPerYieldUnit,
            'category'               => $this->category ?: null,
            'ingredient_category_id' => $this->ingredient_category_id,
            'is_active'              => $this->is_active,
        ];

        if ($this->recipeId) {
            $recipe = Recipe::findOrFail($this->recipeId);
            $recipe->update($data);
            session()->flash('success', 'Recipe updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $recipe = Recipe::create($data);
            session()->flash('success', 'Recipe created.');
        }

        // Sync lines
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

        // Save new images (dine-in)
        $existingDineInCount = count($this->existingDineInImages);
        foreach ($this->newDineInImages as $idx => $file) {
            $path = $file->store('recipe-images', 'public');
            $recipe->images()->create([
                'type'       => 'dine_in',
                'file_name'  => $file->getClientOriginalName(),
                'file_path'  => $path,
                'mime_type'  => $file->getMimeType(),
                'file_size'  => $file->getSize(),
                'sort_order' => $existingDineInCount + $idx,
            ]);
        }

        // Save new images (takeaway)
        $existingTakeawayCount = count($this->existingTakeawayImages);
        foreach ($this->newTakeawayImages as $idx => $file) {
            $path = $file->store('recipe-images', 'public');
            $recipe->images()->create([
                'type'       => 'takeaway',
                'file_name'  => $file->getClientOriginalName(),
                'file_path'  => $path,
                'mime_type'  => $file->getMimeType(),
                'file_size'  => $file->getSize(),
                'sort_order' => $existingTakeawayCount + $idx,
            ]);
        }

        $this->redirectRoute('recipes.index');
    }

    public function render()
    {
        $uoms = UnitOfMeasure::orderBy('name')->get();

        $recipeCategories = RecipeCategory::with(['children' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order')->orderBy('name');
            }])
            ->roots()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = IngredientCategory::roots()->active()->ordered()->get();

        // Ingredient search results (min 2 chars)
        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $existingIds = collect($this->lines)->pluck('ingredient_id')->filter()->toArray();
            $searchResults = Ingredient::with(['baseUom', 'recipeUom', 'uomConversions'])
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        // Live cost calculations
        [$lineCosts, $totalCost] = $this->computeLineCosts();

        $yieldQty     = max(floatval($this->yield_quantity), 0.0001);
        $sellingPrice = floatval($this->selling_price);

        $costPerServing  = $totalCost / $yieldQty;
        $foodCostPct     = $sellingPrice > 0 ? ($totalCost / $sellingPrice) * 100 : null;
        $grossProfit     = $sellingPrice > 0 ? $sellingPrice - $totalCost : null;
        $grossProfitPct  = $sellingPrice > 0 ? (($sellingPrice - $totalCost) / $sellingPrice) * 100 : null;

        $pageTitle = $this->recipeId
            ? 'Edit: ' . ($this->name ?: 'Recipe')
            : 'New Recipe';

        return view('livewire.recipes.form', compact(
            'uoms', 'recipeCategories', 'categories', 'searchResults', 'lineCosts', 'totalCost',
            'costPerServing', 'foodCostPct', 'grossProfit', 'grossProfitPct'
        ))->layout('layouts.app', ['title' => $pageTitle]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function computeLineCosts(): array
    {
        if (empty($this->lines)) return [[], 0.0];

        $ingredientIds = collect($this->lines)->pluck('ingredient_id')->filter()->unique()->values();
        $uomIds        = collect($this->lines)->pluck('uom_id')->filter()->unique()->values();

        $ingredientsMap = $ingredientIds->isNotEmpty()
            ? Ingredient::with(['baseUom', 'uomConversions'])
                ->whereIn('id', $ingredientIds)->get()->keyBy('id')
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
                $costPerUom  = $uomService->convertCost($ingredient, $uom);
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

    /** Strip trailing zeros from a decimal string. */
    private function fmt(mixed $val, int $minDecimals = 0): string
    {
        $float = floatval($val);
        // Use enough precision then strip trailing zeros
        $str = number_format($float, 4, '.', '');
        $str = rtrim($str, '0');
        $str = rtrim($str, '.');
        // Ensure at least $minDecimals decimal places
        if ($minDecimals > 0) {
            $parts = explode('.', $str);
            $decimals = $parts[1] ?? '';
            if (strlen($decimals) < $minDecimals) {
                $str = number_format($float, $minDecimals, '.', '');
            }
        }
        return $str ?: '0';
    }
}
