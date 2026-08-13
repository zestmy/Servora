<?php

namespace App\Livewire\Kitchen;

use App\Models\CentralKitchen;
use App\Models\Ingredient;
use App\Models\ProductionRecipe;
use App\Models\RecipeCategory;
use App\Models\UnitOfMeasure;
use App\Services\UomService;
use App\Services\VisionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductionRecipeForm extends Component
{
    use WithFileUploads;

    public ?int $recipeId = null;

    // Basic
    public string $name        = '';
    public string $code        = '';
    public string $category    = '';
    public ?int   $kitchen_id  = null;
    public string $description = '';
    public string $video_url   = '';

    // Yield
    public string $yield_quantity = '1';
    public ?int   $yield_uom_id  = null;

    // Packaging
    public string $packaging_uom        = '';
    public string $per_carton_qty       = '';
    public string $carton_weight        = '';
    public string $shelf_life_days      = '';
    public string $shelf_life_value     = '';
    public string $shelf_life_unit      = 'days';
    public string $storage_instruction  = '';
    public string $storage_temperature  = '';
    public string $min_batch_size       = '';

    // Product / presentation photos
    public array $newPresentationImages      = [];
    public array $existingPresentationImages = [];

    // Costing inputs
    public string $packaging_cost_per_unit = '0';
    public string $label_cost              = '0';
    public string $selling_price_per_unit  = '0';

    // Calculated (display only)
    public float $raw_material_cost   = 0;
    public float $total_cost_per_unit = 0;
    public float $margin              = 0;
    public float $margin_percent      = 0;

    // Ingredient lines: [{ingredient_id, ingredient_name, quantity, uom_id,
    //  recipe_uom_id, secondary_recipe_uom_id, waste_percentage, unit_cost, line_cost}]
    public array  $lines            = [];
    public string $ingredientSearch = '';

    /*
     * PACKAGING IS A LIST OF INGREDIENTS, as it is on the outlet recipe form,
     * not a number typed into the costing panel. A box has a purchase price
     * that moves; a figure somebody typed in March does not.
     */
    public array  $packagingLines   = [];
    public string $packagingSearch  = '';

    /** [['label' => 'Gas', 'type' => 'value'|'percent', 'amount' => '1.5'], …] */
    public array  $extraCosts = [];

    /** [recipe_price_class_id => 'selling price'] — one price per class. */
    public array  $classPrices = [];

    /** Derived, shown in the read-only summary. */
    public float $packaging_cost  = 0;
    public float $extra_cost_total = 0;

    // Training / SOP steps: [{id, title, instruction, image_path, new_image, remove_image}]
    public array $steps = [];

    protected function rules(): array
    {
        return [
            'name'                     => 'required|string|max:255',
            'code'                     => 'nullable|string|max:100',
            'category'                 => 'nullable|string|max:100',
            'kitchen_id'               => 'required|exists:central_kitchens,id',
            'description'              => 'nullable|string',
            'video_url'                => 'nullable|url|max:500',
            'yield_quantity'           => 'required|numeric|min:0.0001',
            'yield_uom_id'            => 'required|exists:units_of_measure,id',
            'packaging_uom'            => 'nullable|string|max:100',
            'per_carton_qty'           => 'nullable|numeric|min:0',
            'carton_weight'            => 'nullable|numeric|min:0',
            'shelf_life_days'          => 'nullable|integer|min:0',
            'shelf_life_value'         => 'nullable|numeric|min:0',
            'shelf_life_unit'          => 'nullable|in:' . implode(',', array_keys(\App\Models\Recipe::SHELF_LIFE_UNITS)),
            'storage_instruction'      => 'nullable|in:' . implode(',', array_keys(\App\Models\Recipe::STORAGE_OPTIONS)),
            'storage_temperature'      => 'nullable|string|max:50',
            'min_batch_size'           => 'nullable|numeric|min:0',
            'newPresentationImages.*'  => 'nullable|image|max:4096',
            'packaging_cost_per_unit'  => 'nullable|numeric|min:0',
            'label_cost'               => 'nullable|numeric|min:0',
            'selling_price_per_unit'   => 'nullable|numeric|min:0',
            'lines'                    => 'required|array|min:1',
            'lines.*.ingredient_id'    => 'required|exists:ingredients,id',
            'lines.*.quantity'         => 'required|numeric|min:0.0001',
            'lines.*.uom_id'          => 'required|exists:units_of_measure,id',
            'lines.*.waste_percentage' => 'nullable|numeric|min:0|max:100',
            // Packaging costs through the same path as any other line.
            'packagingLines.*.ingredient_id'    => 'required|exists:ingredients,id',
            'packagingLines.*.quantity'         => 'required|numeric|min:0.0001',
            'packagingLines.*.uom_id'           => 'required|exists:units_of_measure,id',
            'packagingLines.*.waste_percentage' => 'nullable|numeric|min:0|max:100',
            'extraCosts.*.label'       => 'required|string|max:100',
            'extraCosts.*.type'        => 'required|in:value,percent',
            'extraCosts.*.amount'      => 'required|numeric|min:0',
            'classPrices.*'            => 'nullable|numeric|min:0',
            'steps.*.new_image'        => 'nullable|image|max:4096',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'              => 'Recipe name is required.',
            'kitchen_id.required'        => 'Please select a kitchen.',
            'yield_quantity.required'     => 'Yield quantity is required.',
            'yield_uom_id.required'      => 'Yield UOM is required.',
            'video_url.url'              => 'The training video link must be a valid URL.',
            'lines.required'             => 'Add at least one ingredient line.',
            'lines.min'                  => 'Add at least one ingredient line.',
            'lines.*.quantity.min'       => 'Quantity must be greater than zero.',
        ];
    }

    public function mount(?int $id = null): void
    {
        // A row per price class, so the form shows every class the company
        // defined rather than only the ones this recipe happens to price.
        foreach (\App\Models\RecipePriceClass::kitchenScope()->ordered()->get() as $class) {
            $this->classPrices[$class->id] = '0';
        }

        if (! $id) return;

        $recipe = ProductionRecipe::with(['lines.ingredient', 'lines.uom', 'steps', 'prices'])->findOrFail($id);

        $this->recipeId               = $recipe->id;
        $this->name                   = $recipe->name;
        $this->code                   = $recipe->code ?? '';
        $this->category               = $recipe->category ?? '';
        $this->kitchen_id             = $recipe->kitchen_id;
        $this->description            = $recipe->description ?? '';
        $this->video_url              = $recipe->video_url ?? '';
        $this->yield_quantity         = (string) floatval($recipe->yield_quantity);
        $this->yield_uom_id          = $recipe->yield_uom_id;
        $this->packaging_uom          = $recipe->packaging_uom ?? '';
        $this->per_carton_qty         = $recipe->per_carton_qty ? (string) $recipe->per_carton_qty : '';
        $this->carton_weight          = $recipe->carton_weight ? (string) floatval($recipe->carton_weight) : '';
        $this->shelf_life_days        = $recipe->shelf_life_days ? (string) $recipe->shelf_life_days : '';
        $this->shelf_life_value       = $recipe->shelf_life_value ? (string) floatval($recipe->shelf_life_value) : '';
        $this->shelf_life_unit        = $recipe->shelf_life_unit ?: 'days';
        $this->storage_instruction    = $recipe->storage_instruction ?? '';
        $this->storage_temperature    = $recipe->storage_temperature ?? '';
        $this->min_batch_size         = $recipe->min_batch_size ? (string) floatval($recipe->min_batch_size) : '';
        $this->packaging_cost_per_unit = (string) floatval($recipe->packaging_cost_per_unit);
        $this->label_cost             = (string) floatval($recipe->label_cost);
        $this->selling_price_per_unit = (string) floatval($recipe->selling_price_per_unit);

        $mapLine = fn ($l) => [
            'ingredient_id'           => $l->ingredient_id,
            'ingredient_name'         => $l->ingredient?->name ?? '-',
            'quantity'                => (string) floatval($l->quantity),
            'uom_id'                  => $l->uom_id,
            'uom_name'                => $l->uom?->abbreviation ?? '-',
            'recipe_uom_id'           => $l->ingredient?->recipe_uom_id,
            'secondary_recipe_uom_id' => $l->ingredient?->secondary_recipe_uom_id,
            'waste_percentage'        => (string) floatval($l->waste_percentage),
            'unit_cost'               => '0',
            'line_cost'               => null,
        ];

        $this->lines          = $recipe->lines->where('is_packaging', false)->values()->map($mapLine)->toArray();
        $this->packagingLines = $recipe->lines->where('is_packaging', true)->values()->map($mapLine)->toArray();

        $this->extraCosts = collect($recipe->extra_costs ?? [])->map(fn ($c) => [
            'label'  => $c['label'] ?? '',
            'type'   => $c['type'] ?? 'value',
            'amount' => (string) floatval($c['amount'] ?? 0),
        ])->toArray();

        foreach ($recipe->prices as $price) {
            $this->classPrices[$price->recipe_price_class_id] = (string) floatval($price->selling_price);
        }

        $this->steps = $recipe->steps->map(fn ($s) => [
            'id'           => $s->id,
            'title'        => $s->title ?? '',
            'instruction'  => $s->instruction,
            'image_path'   => $s->image_path,
            'new_image'    => null,
            'remove_image' => false,
        ])->toArray();

        $this->loadPresentationImages();
        $this->recalculate();
    }

    // ── Ingredient Search & Add ─────────────────────────────────────────

    public function addIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::with(['baseUom', 'recipeUom'])->find($ingredientId);
        if (! $ingredient) return;

        // Skip duplicates
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        // Default to the ingredient's RECIPE unit — that is the unit recipes
        // are written in; the base/pack unit is for purchasing.
        $defaultUomId = $ingredient->recipe_uom_id ?? $ingredient->base_uom_id;

        $this->lines[] = [
            'ingredient_id'           => $ingredientId,
            'ingredient_name'         => $ingredient->name,
            'quantity'                => '1',
            'uom_id'                  => $defaultUomId,
            'uom_name'                => $ingredient->recipeUom?->abbreviation ?? $ingredient->baseUom?->abbreviation ?? '-',
            'recipe_uom_id'           => $ingredient->recipe_uom_id,
            'secondary_recipe_uom_id' => $ingredient->secondary_recipe_uom_id,
            'waste_percentage'        => '0',
            'unit_cost'               => '0',
            'line_cost'               => null,
        ];

        $this->ingredientSearch = '';
        $this->recalculate();
    }

    /** Packaging behaves exactly like an ingredient — same table, own flag. */
    public function addPackaging(int $ingredientId): void
    {
        $ingredient = Ingredient::with(['baseUom', 'recipeUom'])->find($ingredientId);
        if (! $ingredient) return;

        if (! $ingredient->is_active) {
            $this->addError('packagingLines', "'{$ingredient->name}' is inactive. Activate it in the Ingredients list first.");
            return;
        }

        foreach ($this->packagingLines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->packagingSearch = '';
                return;
            }
        }

        $this->packagingLines[] = [
            'ingredient_id'           => $ingredient->id,
            'ingredient_name'         => $ingredient->name,
            'quantity'                => '1',
            'uom_id'                  => $ingredient->recipe_uom_id ?? $ingredient->base_uom_id,
            'uom_name'                => $ingredient->recipeUom?->abbreviation ?? $ingredient->baseUom?->abbreviation ?? '-',
            'recipe_uom_id'           => $ingredient->recipe_uom_id,
            'secondary_recipe_uom_id' => $ingredient->secondary_recipe_uom_id,
            'waste_percentage'        => '0',
            'unit_cost'               => '0',
            'line_cost'               => null,
        ];

        $this->packagingSearch = '';
        $this->recalculate();
    }

    public function removePackagingLine(int $idx): void
    {
        unset($this->packagingLines[$idx]);
        $this->packagingLines = array_values($this->packagingLines);
        $this->recalculate();
    }

    public function updatedPackagingLines(): void
    {
        $this->recalculate();
    }

    public function addExtraCostRow(): void
    {
        $this->extraCosts[] = ['label' => '', 'type' => 'value', 'amount' => '0'];
    }

    public function removeExtraCostRow(int $idx): void
    {
        unset($this->extraCosts[$idx]);
        $this->extraCosts = array_values($this->extraCosts);
        $this->recalculate();
    }

    public function updatedExtraCosts(): void
    {
        $this->recalculate();
    }

    public function updatedClassPrices(): void
    {
        $this->recalculate();
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
        $this->recalculate();
    }

    /** Drag-and-drop line reordering (SortableJS posts the new index order). */
    public function reorderLines(array $orderedIndexes): void
    {
        $reordered = [];
        foreach ($orderedIndexes as $idx) {
            if (isset($this->lines[(int) $idx])) {
                $reordered[] = $this->lines[(int) $idx];
            }
        }
        if (count($reordered) === count($this->lines)) {
            $this->lines = $reordered;
        }
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

    // ── Costing Calculation (UOM-aware, same basis as the prep item form) ──

    /**
     * Cost one list of lines, writing unit_cost and line_cost back into it.
     *
     * Shared by ingredients and packaging so the two cannot drift: packaging
     * used to be a number typed into the summary, which is exactly how a box
     * ends up costed at last year's price.
     *
     * @param  array<int, array>  $lines  by reference — costs are written back
     */
    private function costLines(array &$lines): float
    {
        if (empty($lines)) {
            return 0.0;
        }

        $ingredientIds = collect($lines)->pluck('ingredient_id')->filter()->unique()->values();
        $uomIds        = collect($lines)->pluck('uom_id')->filter()->unique()->values();

        $ingredientsMap = $ingredientIds->isNotEmpty()
            ? Ingredient::with(['baseUom', 'uomConversions'])->whereIn('id', $ingredientIds)->get()->keyBy('id')
            : collect();
        $uomsMap = $uomIds->isNotEmpty()
            ? UnitOfMeasure::whereIn('id', $uomIds)->get()->keyBy('id')
            : collect();

        $uomService = app(UomService::class);
        $total      = 0.0;

        foreach ($lines as $idx => $line) {
            $ingredient = $ingredientsMap->get($line['ingredient_id'] ?? 0);
            $uom        = $uomsMap->get($line['uom_id'] ?? 0);
            $qty        = floatval($line['quantity'] ?? 0);

            if ($ingredient && $uom && $qty > 0) {
                if ($ingredient->is_prep) {
                    $costPerUom = $uomService->convertCost($ingredient, $uom);
                } else {
                    // Pre-yield basis: purchase_price / pack_size, converted
                    // to the line's unit.
                    $originalCost = $ingredient->current_cost;
                    $packSize = max((float) $ingredient->pack_size, 0.0001);
                    $ingredient->current_cost = (float) $ingredient->purchase_price / $packSize;
                    $costPerUom = $uomService->convertCost($ingredient, $uom);
                    $ingredient->current_cost = $originalCost;
                }

                $wasteFactor = 1 + (floatval($line['waste_percentage'] ?? 0) / 100);
                $lineCost    = $costPerUom * $wasteFactor * $qty;

                $lines[$idx]['unit_cost'] = (string) round($costPerUom, 4);
                $lines[$idx]['line_cost'] = round($lineCost, 4);
                $total += $lineCost;
            } else {
                $lines[$idx]['line_cost'] = null;
            }
        }

        return $total;
    }

    /**
     * Everything the read-only summary shows.
     *
     * Cost/unit = (raw + packaging + extras + label) / yield. Margin is quoted
     * against the DEFAULT price class, because a single margin figure has to
     * be against one price and the default is the one the kitchen sells at
     * unless told otherwise.
     */
    private function recalculate(): void
    {
        $rawCost   = $this->costLines($this->lines);
        $packaging = $this->costLines($this->packagingLines);

        // A percentage extra is a percentage OF THE RAW MATERIAL COST, the
        // same base the outlet recipe form uses — not of the running total,
        // which would make two extras depend on the order they were added.
        $extras = collect($this->extraCosts)->sum(function ($c) use ($rawCost) {
            $amount = floatval($c['amount'] ?? 0);

            return ($c['type'] ?? 'value') === 'percent' ? $rawCost * ($amount / 100) : $amount;
        });

        $yield       = max(floatval($this->yield_quantity), 0.0001);
        $label       = floatval($this->label_cost);
        $costPerUnit = ($rawCost + $packaging + $extras + $label) / $yield;

        $selling   = floatval($this->defaultClassPrice());
        $margin    = $selling - $costPerUnit;
        $marginPct = $selling > 0 ? ($margin / $selling) * 100 : 0;

        $this->raw_material_cost   = round($rawCost, 4);
        $this->packaging_cost      = round($packaging, 4);
        $this->extra_cost_total    = round((float) $extras, 4);
        $this->total_cost_per_unit = round($costPerUnit, 4);
        $this->margin              = round($margin, 4);
        $this->margin_percent      = round($marginPct, 2);
    }

    /**
     * The price the summary quotes its margin against: the default class, or
     * the first one priced when no class is marked default.
     */
    /** Extra costs worth storing: a row with no label is a row half typed. */
    private function cleanExtraCosts(): array
    {
        return collect($this->extraCosts)
            ->filter(fn ($c) => trim($c['label'] ?? '') !== '')
            ->map(fn ($c) => [
                'label'  => trim($c['label']),
                'type'   => in_array($c['type'] ?? 'value', ['value', 'percent'], true) ? $c['type'] : 'value',
                'amount' => round(floatval($c['amount'] ?? 0), 4),
            ])
            ->values()
            ->all();
    }

    /** The summary quotes its margin against this. Public for the view. */
    public function defaultSellingPrice(): string
    {
        return $this->defaultClassPrice();
    }

    private function defaultClassPrice(): string
    {
        $classes = \App\Models\RecipePriceClass::kitchenScope()->ordered()->get();

        $default = $classes->firstWhere('is_default', true) ?? $classes->first();

        if ($default && ($this->classPrices[$default->id] ?? '') !== '') {
            return (string) $this->classPrices[$default->id];
        }

        foreach ($this->classPrices as $price) {
            if (floatval($price) > 0) {
                return (string) $price;
            }
        }

        return '0';
    }

    // ── Product / presentation photos ───────────────────────────────────

    private function loadPresentationImages(): void
    {
        if (! $this->recipeId) {
            $this->existingPresentationImages = [];
            return;
        }

        $this->existingPresentationImages = \App\Models\ProductionRecipeImage::where('production_recipe_id', $this->recipeId)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($img) => [
                'id'        => $img->id,
                'file_path' => $img->file_path,
                'file_name' => $img->file_name,
            ])->toArray();
    }

    public function updatedNewPresentationImages(): void
    {
        $this->validate(['newPresentationImages.*' => 'nullable|image|max:4096']);
    }

    public function removeNewPresentationImage(int $idx): void
    {
        unset($this->newPresentationImages[$idx]);
        $this->newPresentationImages = array_values($this->newPresentationImages);
    }

    public function deletePresentationImage(int $imageId): void
    {
        $img = \App\Models\ProductionRecipeImage::where('production_recipe_id', $this->recipeId)->find($imageId);
        if (! $img) return;

        Storage::disk('public')->delete($img->file_path);
        $img->delete();
        $this->loadPresentationImages();
    }

    /** Absolute paths to product photos — saved first, then pending uploads (max 3, for AI prompts). */
    private function collectDishImagePaths(): array
    {
        $paths = [];

        foreach ($this->existingPresentationImages as $img) {
            if (count($paths) >= 3) break;
            $path = Storage::disk('public')->path($img['file_path']);
            if (is_file($path)) $paths[] = $path;
        }

        foreach ($this->newPresentationImages as $upload) {
            if (count($paths) >= 3) break;
            if (is_object($upload) && method_exists($upload, 'getRealPath')) {
                $real = $upload->getRealPath();
                if ($real && is_file($real)) $paths[] = $real;
            }
        }

        return array_slice($paths, 0, 3);
    }

    // ── Training / SOP steps ────────────────────────────────────────────

    public function addStep(): void
    {
        $this->steps[] = [
            'id'           => null,
            'title'        => '',
            'instruction'  => '',
            'image_path'   => null,
            'new_image'    => null,
            'remove_image' => false,
        ];
    }

    public function removeStep(int $idx): void
    {
        unset($this->steps[$idx]);
        $this->steps = array_values($this->steps);
    }

    public function removeStepImage(int $idx): void
    {
        if (isset($this->steps[$idx])) {
            $this->steps[$idx]['remove_image'] = true;
            $this->steps[$idx]['new_image']    = null;
        }
    }

    public function clearStepNewImage(int $idx): void
    {
        if (isset($this->steps[$idx])) {
            $this->steps[$idx]['new_image'] = null;
        }
    }

    // ── AI step assistance (same VisionService as the prep item form) ───

    public function suggestPreparationSteps(string $mode = 'append'): void
    {
        $ingredientNames = $this->recipeIngredientNames();

        if (trim($this->name) === '' && empty($ingredientNames)) {
            session()->flash('ai_steps_error', 'Add a recipe name and at least one ingredient before generating steps.');
            return;
        }

        try {
            $suggested = app(VisionService::class)->suggestPreparationSteps($this->name, $ingredientNames, $this->collectDishImagePaths());
        } catch (\Throwable $e) {
            session()->flash('ai_steps_error', $e->getMessage());
            return;
        }

        if ($mode === 'replace') {
            $this->steps = [];
        } else {
            $this->steps = array_values(array_filter($this->steps, fn ($s) => trim($s['instruction'] ?? '') !== ''));
        }

        foreach ($suggested as $s) {
            $this->steps[] = [
                'id'           => null,
                'title'        => $s['title'] ?? '',
                'instruction'  => $s['instruction'] ?? '',
                'image_path'   => null,
                'new_image'    => null,
                'remove_image' => false,
            ];
        }

        $verb = $mode === 'replace' ? 'replaced all steps' : 'added';
        session()->flash('ai_steps_success', count($suggested) . " AI-suggested step(s) {$verb}. Review and edit before saving.");
    }

    public function regenerateStep(int $idx): void
    {
        if (! isset($this->steps[$idx])) return;

        $ingredientNames = $this->recipeIngredientNames();
        if (trim($this->name) === '' && empty($ingredientNames)) {
            session()->flash('ai_steps_error', 'Add a recipe name and at least one ingredient before regenerating.');
            return;
        }

        $existing = array_map(fn ($s) => [
            'title'       => $s['title'] ?? '',
            'instruction' => $s['instruction'] ?? '',
        ], $this->steps);

        try {
            $new = app(VisionService::class)->regeneratePreparationStep(
                $this->name, $ingredientNames, $existing, $idx + 1, $this->collectDishImagePaths(),
            );
        } catch (\Throwable $e) {
            session()->flash('ai_steps_error', $e->getMessage());
            return;
        }

        $this->steps[$idx]['title']       = $new['title'] ?? $this->steps[$idx]['title'];
        $this->steps[$idx]['instruction'] = $new['instruction'] ?? $this->steps[$idx]['instruction'];

        session()->flash('ai_steps_success', 'Step ' . ($idx + 1) . ' regenerated.');
    }

    public function fineTuneSteps(): void
    {
        $indexes = [];
        foreach ($this->steps as $i => $s) {
            if (trim($s['instruction'] ?? '') !== '') $indexes[] = $i;
        }

        if (empty($indexes)) {
            session()->flash('ai_steps_error', 'Add at least one step with an instruction before fine-tuning.');
            return;
        }

        $payload = array_map(fn ($i) => [
            'title'       => $this->steps[$i]['title'] ?? '',
            'instruction' => $this->steps[$i]['instruction'] ?? '',
        ], $indexes);

        try {
            $polished = app(VisionService::class)->fineTunePreparationSteps(
                $this->name, $this->recipeIngredientNames(), $payload,
            );
        } catch (\Throwable $e) {
            session()->flash('ai_steps_error', $e->getMessage());
            return;
        }

        $changed = 0;
        foreach ($indexes as $k => $i) {
            $newTitle = $polished[$k]['title'] ?? ($this->steps[$i]['title'] ?? '');
            $newInstr = $polished[$k]['instruction'] ?? $this->steps[$i]['instruction'];
            if ($newTitle !== ($this->steps[$i]['title'] ?? '') || $newInstr !== $this->steps[$i]['instruction']) {
                $changed++;
            }
            $this->steps[$i]['title']       = $newTitle;
            $this->steps[$i]['instruction'] = $newInstr;
        }

        session()->flash('ai_steps_success', $changed === 0
            ? 'All steps already read well — no changes needed.'
            : "{$changed} step(s) fine-tuned for spelling and clarity. Review and save.");
    }

    private function recipeIngredientNames(): array
    {
        return collect($this->lines)
            ->pluck('ingredient_name')
            ->filter(fn ($n) => $n && $n !== '—' && $n !== '-')
            ->values()
            ->all();
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
                'video_url'              => $this->video_url ?: null,
                'yield_quantity'         => floatval($this->yield_quantity),
                'yield_uom_id'          => $this->yield_uom_id,
                'packaging_uom'          => $this->packaging_uom ?: null,
                'per_carton_qty'         => $this->per_carton_qty ? (int) $this->per_carton_qty : null,
                'carton_weight'          => $this->carton_weight ? floatval($this->carton_weight) : null,
                'shelf_life_days'        => $this->shelf_life_days ? (int) $this->shelf_life_days : null,
                'shelf_life_value'       => $this->shelf_life_value !== '' ? floatval($this->shelf_life_value) : null,
                'shelf_life_unit'        => $this->shelf_life_value !== '' ? ($this->shelf_life_unit ?: 'days') : null,
                'storage_instruction'    => $this->storage_instruction ?: null,
                'storage_temperature'    => $this->storage_temperature ?: null,
                'min_batch_size'         => $this->min_batch_size !== '' ? floatval($this->min_batch_size) : null,
                /*
                 * The two legacy columns are still written, from the values
                 * the new sections DERIVE, so everything downstream that reads
                 * them — production orders price their lines off
                 * total_cost_per_unit — keeps working unchanged.
                 */
                'packaging_cost_per_unit' => $this->packaging_cost,
                'label_cost'             => floatval($this->label_cost),
                'extra_costs'            => $this->cleanExtraCosts(),
                'raw_material_cost'      => $this->raw_material_cost,
                'total_cost_per_unit'    => $this->total_cost_per_unit,
                'selling_price_per_unit' => floatval($this->defaultClassPrice()),
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

            // Keep the paired stock item in step with the recipe. Without it a
            // completed production batch would have nowhere to put its output.
            $recipe->syncStockItem();

            // Sync lines. Ingredients and packaging share the table and are
            // told apart by is_packaging, as they are on outlet recipes.
            $recipe->lines()->delete();
            foreach ([false => $this->lines, true => $this->packagingLines] as $isPackaging => $lines) {
                foreach ($lines as $idx => $line) {
                    $recipe->lines()->create([
                        'ingredient_id'    => $line['ingredient_id'],
                        'is_packaging'     => (bool) $isPackaging,
                        'quantity'         => floatval($line['quantity']),
                        'uom_id'           => $line['uom_id'],
                        'waste_percentage' => floatval($line['waste_percentage'] ?? 0),
                        'sort_order'       => $idx + 1,
                    ]);
                }
            }

            // One row per class that has a price. A class left at zero is not
            // stored, so adding a class later does not backfill every recipe
            // with a zero somebody has to notice and clear.
            $recipe->prices()->delete();
            foreach ($this->classPrices as $classId => $price) {
                if (floatval($price) <= 0) continue;

                $recipe->prices()->create([
                    'recipe_price_class_id' => (int) $classId,
                    'selling_price'         => floatval($price),
                ]);
            }

            // Sync training steps (upsert, preserve images)
            $keepStepIds = [];
            foreach ($this->steps as $idx => $step) {
                if (trim($step['instruction'] ?? '') === '') continue;

                $imagePath = $step['image_path'] ?? null;

                if (! empty($step['remove_image']) && $imagePath) {
                    Storage::disk('public')->delete($imagePath);
                    $imagePath = null;
                }

                if (! empty($step['new_image']) && is_object($step['new_image'])) {
                    if ($imagePath) {
                        Storage::disk('public')->delete($imagePath);
                    }
                    $imagePath = \App\Services\ImageStorageService::storeCompressed($step['new_image'], 'production-recipe-steps');
                }

                $stepData = [
                    'sort_order'  => $idx,
                    'title'       => $step['title'] ?: null,
                    'instruction' => $step['instruction'],
                    'image_path'  => $imagePath,
                ];

                if (! empty($step['id'])) {
                    $existing = $recipe->steps()->find($step['id']);
                    if ($existing) {
                        $existing->update($stepData);
                        $keepStepIds[] = $existing->id;
                        continue;
                    }
                }

                $newStep = $recipe->steps()->create($stepData);
                $keepStepIds[] = $newStep->id;
            }
            $recipe->steps()->whereNotIn('id', $keepStepIds ?: [0])->get()->each(function ($s) {
                if ($s->image_path) {
                    Storage::disk('public')->delete($s->image_path);
                }
                $s->delete();
            });

            // Store newly uploaded product photos
            if (! empty($this->newPresentationImages)) {
                $nextSort = (int) $recipe->images()->max('sort_order');
                foreach ($this->newPresentationImages as $upload) {
                    if (! is_object($upload)) continue;
                    $path = \App\Services\ImageStorageService::storeCompressed($upload, 'production-recipe-images');
                    $recipe->images()->create([
                        'file_name'  => $upload->getClientOriginalName(),
                        'file_path'  => $path,
                        'mime_type'  => $upload->getMimeType(),
                        'file_size'  => Storage::disk('public')->size($path),
                        'sort_order' => ++$nextSort,
                    ]);
                }
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

        // Central Kitchen's own category list — deliberately separate from the
        // outlet menu categories used by recipes and prep items.
        $recipeCategories = RecipeCategory::with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->kitchenScope()
            ->roots()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

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

        $packagingSearchResults = collect();
        if (strlen($this->packagingSearch) >= 2) {
            $chosen = collect($this->packagingLines)->pluck('ingredient_id')->filter()->all();

            $packagingSearchResults = Ingredient::with('baseUom')
                ->where('is_active', true)
                ->where(fn ($q) => $q
                    ->where('name', 'like', '%' . $this->packagingSearch . '%')
                    ->orWhere('code', 'like', '%' . $this->packagingSearch . '%')
                )
                ->when($chosen, fn ($q) => $q->whereNotIn('id', $chosen))
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        // The outlet recipes' classes, not a second set.
        $priceClasses = \App\Models\RecipePriceClass::kitchenScope()->ordered()->get();

        $pageTitle = $this->recipeId
            ? 'Edit: ' . $this->name
            : 'New Production Recipe';

        return view('livewire.kitchen.production-recipe-form', compact(
            'kitchens', 'uoms', 'searchResults', 'recipeCategories',
            'packagingSearchResults', 'priceClasses'
        ))
            ->layout('layouts.kitchen', ['title' => $pageTitle]);
    }
}
