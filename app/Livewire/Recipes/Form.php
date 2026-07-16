<?php

namespace App\Livewire\Recipes;

use App\Models\CentralKitchen;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\OutletGroup;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeImage;
use App\Models\RecipePriceClass;
use App\Models\RecipeStep;
use App\Models\UnitOfMeasure;
use App\Services\UomService;
use App\Services\VisionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;
    use \App\Traits\RejectsUnpreviewableUploads;

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
    public ?int   $department_id = null;
    public bool   $is_active = true;
    public bool   $exclude_from_lms = false;

    // Outlet tagging
    public bool  $allOutlets = true;
    public array $outletIds = [];

    // Ingredient lines: each row = [ingredient_id, ingredient_name, quantity, uom_id, waste_percentage]
    public array $lines = [];

    // Packaging lines: same shape as $lines; stored with is_packaging=true
    public array $packagingLines = [];

    // Images (dine-in & takeaway)
    public array $newDineInImages = [];
    public array $newTakeawayImages = [];
    public array $existingDineInImages = [];
    public array $existingTakeawayImages = [];

    // Extra costs: each row = [label, amount]
    public array $extraCosts = [];

    // Training / SOP
    public string $video_url = '';
    public array $steps = [];

    // Multi-price: [price_class_id => selling_price_string]
    public array $classPrices = [];

    // Ingredient search
    public string $ingredientSearch = '';
    public string $packagingSearch = '';

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
            'department_id'              => 'nullable|exists:departments,id',
            'lines.*.ingredient_id'      => 'required|exists:ingredients,id',
            'lines.*.quantity'           => 'required|numeric|min:0.0001',
            'lines.*.uom_id'             => 'required|exists:units_of_measure,id',
            'lines.*.waste_percentage'   => 'required|numeric|min:0|max:100',
            'packagingLines.*.ingredient_id'    => 'required|exists:ingredients,id',
            'packagingLines.*.quantity'         => 'required|numeric|min:0.0001',
            'packagingLines.*.uom_id'           => 'required|exists:units_of_measure,id',
            'packagingLines.*.waste_percentage' => 'required|numeric|min:0|max:100',
            'newDineInImages.*'          => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'newTakeawayImages.*'        => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'extraCosts.*.label'         => 'required|string|max:100',
            'extraCosts.*.type'          => 'required|in:value,percent',
            'extraCosts.*.amount'        => 'required|numeric|min:0',
            'video_url'                  => 'nullable|url|max:500',
            'steps.*.title'              => 'nullable|string|max:255',
            'steps.*.instruction'        => 'required|string',
            'steps.*.new_image'          => 'nullable|image|mimes:jpeg,jpg,png,webp,gif|max:5120',
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
            'extraCosts.*.label.required'        => 'Cost label is required.',
            'extraCosts.*.amount.required'       => 'Cost amount is required.',
            'extraCosts.*.amount.min'            => 'Cost amount cannot be negative.',
        ];
    }

    public function mount(?int $id = null): void
    {
        // Initialise class prices for all price classes
        $priceClasses = RecipePriceClass::ordered()->get();
        foreach ($priceClasses as $pc) {
            $this->classPrices[$pc->id] = '0';
        }

        if (! $id) return;

        $recipe = Recipe::with(['lines.ingredient', 'lines.uom', 'images', 'outlets', 'steps', 'prices'])->findOrFail($id);

        $this->recipeId               = $recipe->id;
        $this->name                   = $recipe->name;
        $this->code                   = $recipe->code ?? '';
        $this->description            = $recipe->description ?? '';
        $this->yield_quantity         = $this->fmt($recipe->yield_quantity);
        $this->yield_uom_id           = $recipe->yield_uom_id;
        $this->selling_price          = $this->fmt($recipe->selling_price);
        $this->category               = $recipe->category ?? '';
        $this->ingredient_category_id = $recipe->ingredient_category_id;
        $this->department_id          = $recipe->department_id;
        $this->is_active              = $recipe->is_active;
        $this->exclude_from_lms       = (bool) $recipe->exclude_from_lms;

        // Load outlet tags
        $taggedOutletIds = $recipe->outlets->pluck('id')->toArray();
        if (empty($taggedOutletIds)) {
            $this->allOutlets = true;
            $this->outletIds  = [];
        } else {
            $this->allOutlets = false;
            $this->outletIds  = $taggedOutletIds;
        }

        $mapLine = fn ($l) => [
            'ingredient_id'           => $l->ingredient_id,
            'ingredient_name'         => $l->ingredient?->name ?? '—',
            'is_prep'                 => (bool) ($l->ingredient?->is_prep ?? false),
            'quantity'                => $this->fmt($l->quantity),
            'uom_id'                  => $l->uom_id,
            'waste_percentage'        => $this->fmt($l->waste_percentage, 2),
            'recipe_uom_id'           => $l->ingredient?->recipe_uom_id,
            'secondary_recipe_uom_id' => $l->ingredient?->secondary_recipe_uom_id,
        ];
        $this->lines          = $recipe->lines->where('is_packaging', false)->values()->map($mapLine)->toArray();
        $this->packagingLines = $recipe->lines->where('is_packaging', true)->values()->map($mapLine)->toArray();

        $this->extraCosts = is_array($recipe->extra_costs) ? $recipe->extra_costs : [];

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

        $this->video_url = $recipe->video_url ?? '';
        $this->steps = $recipe->steps->map(fn ($s) => [
            'id'              => $s->id,
            'title'           => $s->title ?? '',
            'instruction'     => $s->instruction,
            'image_path'      => $s->image_path,
            'image_url'       => $s->imageUrl(),
            'new_image'       => null,
            'remove_image'    => false,
        ])->toArray();

        // Load existing class prices
        foreach ($recipe->prices as $rp) {
            $this->classPrices[$rp->recipe_price_class_id] = $this->fmt($rp->selling_price);
        }
    }

    public function applyGroup(int $groupId): void
    {
        $group = OutletGroup::with('outlets')->find($groupId);
        if (! $group) return;

        $groupOutletIds = $group->outlets
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($groupOutletIds)) return;

        // Switch to "Selected Outlets" and merge in the group's outlets.
        $this->allOutlets = false;
        $existing = array_map('intval', $this->outletIds);
        $this->outletIds = array_values(array_unique(array_merge($existing, $groupOutletIds)));
    }

    public function clearOutletSelection(): void
    {
        $this->outletIds = [];
    }

    public function addIngredient(int $ingredientId): void
    {
        $ingredient = Ingredient::find($ingredientId);
        if (! $ingredient) return;

        if (! $ingredient->is_active) {
            $this->addError('lines', "'{$ingredient->name}' is inactive. Activate it in the Ingredients list first.");
            return;
        }

        // Skip duplicate
        foreach ($this->lines as $line) {
            if ((int) $line['ingredient_id'] === $ingredientId) {
                $this->ingredientSearch = '';
                return;
            }
        }

        $this->lines[] = [
            'ingredient_id'           => $ingredientId,
            'ingredient_name'         => $ingredient->name,
            'is_prep'                 => (bool) $ingredient->is_prep,
            'quantity'                => '1',
            'uom_id'                  => $ingredient->recipe_uom_id,
            'waste_percentage'        => '0',
            'recipe_uom_id'           => $ingredient->recipe_uom_id,
            'secondary_recipe_uom_id' => $ingredient->secondary_recipe_uom_id,
        ];

        $this->ingredientSearch = '';
    }

    public function removeLine(int $idx): void
    {
        unset($this->lines[$idx]);
        $this->lines = array_values($this->lines);
    }

    /** Receives an array of current line indexes in the new order. */
    public function reorderLines(array $orderedIndexes): void
    {
        $ordered = [];
        foreach ($orderedIndexes as $idx) {
            $idx = (int) $idx;
            if (isset($this->lines[$idx])) {
                $ordered[] = $this->lines[$idx];
            }
        }
        if (count($ordered) === count($this->lines)) {
            $this->lines = $ordered;
        }
    }

    // ── Packaging lines ───────────────────────────────────────────────────

    public function addPackaging(int $ingredientId): void
    {
        $ingredient = Ingredient::find($ingredientId);
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
            'ingredient_id'           => $ingredientId,
            'ingredient_name'         => $ingredient->name,
            'is_prep'                 => (bool) $ingredient->is_prep,
            'quantity'                => '1',
            'uom_id'                  => $ingredient->recipe_uom_id ?? $ingredient->base_uom_id,
            'waste_percentage'        => '0',
            'recipe_uom_id'           => $ingredient->recipe_uom_id,
            'secondary_recipe_uom_id' => $ingredient->secondary_recipe_uom_id,
        ];

        $this->packagingSearch = '';
    }

    public function removePackagingLine(int $idx): void
    {
        unset($this->packagingLines[$idx]);
        $this->packagingLines = array_values($this->packagingLines);
    }

    public function reorderPackagingLines(array $orderedIndexes): void
    {
        $ordered = [];
        foreach ($orderedIndexes as $idx) {
            $idx = (int) $idx;
            if (isset($this->packagingLines[$idx])) {
                $ordered[] = $this->packagingLines[$idx];
            }
        }
        if (count($ordered) === count($this->packagingLines)) {
            $this->packagingLines = $ordered;
        }
    }

    public function updatedNewDineInImages(): void
    {
        $this->newDineInImages = $this->keepPreviewableUploads($this->newDineInImages, 'newDineInImages');
    }

    public function updatedNewTakeawayImages(): void
    {
        $this->newTakeawayImages = $this->keepPreviewableUploads($this->newTakeawayImages, 'newTakeawayImages');
    }

    public function updatedSteps($value, string $key): void
    {
        // Guard step photo uploads ("3.new_image") the same way.
        if (str_ends_with($key, '.new_image')) {
            $idx = (int) $key;
            if (isset($this->steps[$idx])) {
                $this->steps[$idx]['new_image'] = $this->keepPreviewableUpload($this->steps[$idx]['new_image'], "steps.{$idx}.new_image");
            }
        }
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

    public function addExtraCostRow(): void
    {
        $this->extraCosts[] = ['label' => '', 'type' => 'value', 'amount' => '0'];
    }

    public function removeExtraCostRow(int $idx): void
    {
        unset($this->extraCosts[$idx]);
        $this->extraCosts = array_values($this->extraCosts);
    }

    public function addStep(): void
    {
        $this->steps[] = [
            'id' => null, 'title' => '', 'instruction' => '',
            'image_path' => null, 'image_url' => null, 'new_image' => null, 'remove_image' => false,
        ];
    }

    /**
     * Use AI (OpenRouter) to suggest preparation steps from the recipe name,
     * ingredient list, and any dish images.
     *
     * @param  string  $mode  'append' (keep existing steps) or 'replace' (overwrite all).
     */
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
            // Drop blank placeholder steps before appending (non-destructive).
            $this->steps = array_values(array_filter($this->steps, fn ($s) => trim($s['instruction'] ?? '') !== ''));
        }

        foreach ($suggested as $s) {
            $this->steps[] = [
                'id'           => null,
                'title'        => $s['title'] ?? '',
                'instruction'  => $s['instruction'] ?? '',
                'image_path'   => null,
                'image_url'    => null,
                'new_image'    => null,
                'remove_image' => false,
            ];
        }

        $verb = $mode === 'replace' ? 'replaced all steps' : 'added';
        session()->flash('ai_steps_success', count($suggested) . " AI-suggested step(s) {$verb}. Review and edit before saving.");
    }

    /**
     * Regenerate a single preparation step with AI, keeping it consistent with the others.
     */
    public function regenerateStep(int $idx): void
    {
        if (! isset($this->steps[$idx])) {
            return;
        }

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
                $this->name,
                $ingredientNames,
                $existing,
                $idx + 1,
                $this->collectDishImagePaths(),
            );
        } catch (\Throwable $e) {
            session()->flash('ai_steps_error', $e->getMessage());
            return;
        }

        $this->steps[$idx]['title']       = $new['title'] ?? $this->steps[$idx]['title'];
        $this->steps[$idx]['instruction'] = $new['instruction'] ?? $this->steps[$idx]['instruction'];

        session()->flash('ai_steps_success', 'Step ' . ($idx + 1) . ' regenerated.');
    }

    /**
     * Fine-tune all existing steps with AI: fix spelling/grammar and polish the
     * wording into simple, precise SOP-training language. Titles, order, and
     * step count are preserved; images and ids are untouched.
     */
    public function fineTuneSteps(): void
    {
        // Only steps with an instruction are sent; remember their positions so
        // the polished versions map back onto the right rows.
        $indexes = [];
        foreach ($this->steps as $i => $s) {
            if (trim($s['instruction'] ?? '') !== '') {
                $indexes[] = $i;
            }
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
                $this->name,
                $this->recipeIngredientNames(),
                $payload,
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

    /** Ingredient names for the current recipe lines (for AI prompts). */
    private function recipeIngredientNames(): array
    {
        return collect($this->lines)
            ->pluck('ingredient_name')
            ->filter(fn ($n) => $n && $n !== '—')
            ->values()
            ->all();
    }

    /** Absolute paths to dish images — saved product images first, then pending uploads (max 3). */
    private function collectDishImagePaths(): array
    {
        $imagePaths = [];

        if ($this->recipeId) {
            $saved = RecipeImage::where('recipe_id', $this->recipeId)
                ->orderByRaw("CASE WHEN type = 'dine_in' THEN 0 ELSE 1 END")
                ->limit(3)
                ->get();
            foreach ($saved as $img) {
                $path = Storage::disk('public')->path($img->file_path);
                if (is_file($path)) {
                    $imagePaths[] = $path;
                }
            }
        }

        foreach (array_merge($this->newDineInImages, $this->newTakeawayImages) as $upload) {
            if (count($imagePaths) >= 3) {
                break;
            }
            if (is_object($upload) && method_exists($upload, 'getRealPath')) {
                $real = $upload->getRealPath();
                if ($real && is_file($real)) {
                    $imagePaths[] = $real;
                }
            }
        }

        return array_slice($imagePaths, 0, 3);
    }

    public function removeStep(int $idx): void
    {
        unset($this->steps[$idx]);
        $this->steps = array_values($this->steps);
    }

    public function removeStepImage(int $idx): void
    {
        if (! isset($this->steps[$idx])) return;
        // Mark existing image for removal on save; clear any pending new upload
        $this->steps[$idx]['remove_image'] = true;
        $this->steps[$idx]['new_image']    = null;
        $this->steps[$idx]['image_url']    = null;
    }

    public function clearStepNewImage(int $idx): void
    {
        if (isset($this->steps[$idx])) {
            $this->steps[$idx]['new_image'] = null;
        }
    }

    public function save(): void
    {
        $user = Auth::user();
        if ($user?->company?->recipes_locked && ! $user->canBypassLock()) {
            session()->flash('error', 'Recipes are locked. Ask a company admin to unlock in Settings → Company Details.');
            return;
        }

        $this->validate();

        [$lineCosts, $totalCost] = $this->computeLineCosts();

        $extraCostTotal = collect($this->extraCosts)->sum(function ($c) use ($totalCost) {
            if (($c['type'] ?? 'value') === 'percent') {
                return $totalCost * (floatval($c['amount'] ?? 0) / 100);
            }
            return floatval($c['amount'] ?? 0);
        });
        $grandCost      = $totalCost + $extraCostTotal;

        $yieldQty        = max(floatval($this->yield_quantity), 0.0001);
        $costPerYieldUnit = round($grandCost / $yieldQty, 4);

        $data = [
            'name'                   => strtoupper($this->name),
            'code'                   => $this->code ?: null,
            'description'            => $this->description ?: null,
            'video_url'              => $this->video_url ?: null,
            'yield_quantity'         => $this->yield_quantity,
            'yield_uom_id'           => $this->yield_uom_id,
            'selling_price'          => $this->selling_price,
            'cost_per_yield_unit'    => $costPerYieldUnit,
            'category'               => $this->category ?: null,
            'ingredient_category_id' => $this->ingredient_category_id,
            'department_id'          => $this->department_id,
            'is_active'              => $this->is_active,
            'exclude_from_lms'       => $this->exclude_from_lms,
            'extra_costs'            => !empty($this->extraCosts) ? $this->extraCosts : null,
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

        // Sync outlet tags
        if ($this->allOutlets) {
            $recipe->outlets()->detach();
        } else {
            $recipe->outlets()->sync(array_map('intval', $this->outletIds));
        }

        // Capture existing lines for the activity trail before we replace them.
        $auditBefore = [];
        foreach ($recipe->lines()->with(['ingredient', 'uom'])->get() as $l) {
            $auditBefore[(int) $l->ingredient_id] = [
                'item'     => $l->ingredient?->name ?? ('#' . $l->ingredient_id),
                'quantity' => (float) $l->quantity,
                'unit'     => $l->uom?->abbreviation ?? $l->uom?->code,
            ];
        }

        // Sync lines (ingredients + packaging together; keep is_packaging flag)
        $recipe->lines()->delete();
        foreach ($this->lines as $idx => $line) {
            $recipe->lines()->create([
                'ingredient_id'    => $line['ingredient_id'],
                'quantity'         => $line['quantity'],
                'uom_id'           => $line['uom_id'],
                'waste_percentage' => $line['waste_percentage'],
                'sort_order'       => $idx,
                'is_packaging'     => false,
            ]);
        }
        foreach ($this->packagingLines as $idx => $line) {
            $recipe->lines()->create([
                'ingredient_id'    => $line['ingredient_id'],
                'quantity'         => $line['quantity'],
                'uom_id'           => $line['uom_id'],
                'waste_percentage' => $line['waste_percentage'],
                'sort_order'       => $idx,
                'is_packaging'     => true,
            ]);
        }

        // Log ingredient add / remove / quantity changes (edits only — a new
        // recipe's ingredients are implied by its "Created" entry).
        if ($this->recipeId) {
            $auditRows = array_merge($this->lines, $this->packagingLines);
            $ingIds = array_filter(array_map(fn ($l) => (int) ($l['ingredient_id'] ?? 0), $auditRows));
            $uomIds = array_filter(array_map(fn ($l) => (int) ($l['uom_id'] ?? 0), $auditRows));
            $names  = \App\Models\Ingredient::whereIn('id', $ingIds)->pluck('name', 'id');
            $uoms   = \App\Models\UnitOfMeasure::whereIn('id', $uomIds)->pluck('abbreviation', 'id');
            $auditAfter = [];
            foreach ($auditRows as $l) {
                $ingId = (int) ($l['ingredient_id'] ?? 0);
                if (! $ingId) continue;
                $auditAfter[$ingId] = [
                    'item'     => $names[$ingId] ?? ('#' . $ingId),
                    'quantity' => (float) ($l['quantity'] ?? 0),
                    'unit'     => $uoms[(int) ($l['uom_id'] ?? 0)] ?? null,
                ];
            }
            \App\Services\AuditLogService::logLineChanges($recipe, $auditBefore, $auditAfter);
        }

        // Sync steps (upsert so images aren't lost)
        $keepIds = [];
        foreach ($this->steps as $idx => $step) {
            if (trim($step['instruction'] ?? '') === '') continue;

            // Handle image upload
            $imagePath = $step['image_path'] ?? null;

            if (! empty($step['remove_image']) && $imagePath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
                $imagePath = null;
            }

            if (! empty($step['new_image']) && is_object($step['new_image'])) {
                // Delete old image if replacing
                if ($imagePath) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
                }
                $imagePath = \App\Services\ImageStorageService::storeCompressed($step['new_image'], 'recipe-steps');
            }

            $data = [
                'sort_order'  => $idx,
                'title'       => $step['title'] ?: null,
                'instruction' => $step['instruction'],
                'image_path'  => $imagePath,
            ];

            if (! empty($step['id'])) {
                $existing = $recipe->steps()->find($step['id']);
                if ($existing) {
                    $existing->update($data);
                    $keepIds[] = $existing->id;
                    continue;
                }
            }

            $new = $recipe->steps()->create($data);
            $keepIds[] = $new->id;
        }
        // Delete steps that were removed, along with their images
        $recipe->steps()->whereNotIn('id', $keepIds)->get()->each(function ($s) {
            if ($s->image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($s->image_path);
            }
            $s->delete();
        });

        // Save new images (dine-in) — compressed for viewing speed + storage
        $existingDineInCount = count($this->existingDineInImages);
        foreach ($this->newDineInImages as $idx => $file) {
            $path = \App\Services\ImageStorageService::storeCompressed($file, 'recipe-images');
            $disk = Storage::disk('public');
            $recipe->images()->create([
                'type'       => 'dine_in',
                'file_name'  => $file->getClientOriginalName(),
                'file_path'  => $path,
                'mime_type'  => $disk->mimeType($path) ?: $file->getMimeType(),
                'file_size'  => $disk->size($path),
                'sort_order' => $existingDineInCount + $idx,
            ]);
        }

        // Save new images (takeaway)
        $existingTakeawayCount = count($this->existingTakeawayImages);
        foreach ($this->newTakeawayImages as $idx => $file) {
            $path = \App\Services\ImageStorageService::storeCompressed($file, 'recipe-images');
            $disk = Storage::disk('public');
            $recipe->images()->create([
                'type'       => 'takeaway',
                'file_name'  => $file->getClientOriginalName(),
                'file_path'  => $path,
                'mime_type'  => $disk->mimeType($path) ?: $file->getMimeType(),
                'file_size'  => $disk->size($path),
                'sort_order' => $existingTakeawayCount + $idx,
            ]);
        }

        // Sync class prices
        $recipe->prices()->delete();
        foreach ($this->classPrices as $classId => $price) {
            if (floatval($price) > 0) {
                $recipe->prices()->create([
                    'recipe_price_class_id' => $classId,
                    'selling_price'         => $price,
                ]);
            }
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

        $departments = \App\Models\Department::active()->ordered()->get();
        $priceClasses = RecipePriceClass::ordered()->get();

        // Central kitchen outlets are selectable too, so recipes can be tagged
        // as visible to the central kitchen. Their ids are passed along so the
        // picker can badge them.
        $centralKitchenOutletIds = CentralKitchen::whereNotNull('outlet_id')
            ->pluck('outlet_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $outletGroups = OutletGroup::with('outlets')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($g) {
                return (object) [
                    'id'         => $g->id,
                    'name'       => $g->name,
                    'outlet_ids' => $g->outlets->pluck('id')->values()->all(),
                ];
            })
            ->filter(fn ($g) => count($g->outlet_ids) > 0)
            ->values();

        // Ingredient search results (min 2 chars)
        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $existingIds = collect($this->lines)->pluck('ingredient_id')->filter()->toArray();
            $searchResults = Ingredient::with(['baseUom', 'recipeUom', 'secondaryRecipeUom', 'uomConversions'])
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%');
                })
                ->when($existingIds, fn ($q) => $q->whereNotIn('id', $existingIds))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->limit(10)
                ->get();
        }

        // Packaging search results (min 2 chars)
        $packagingSearchResults = collect();
        if (strlen($this->packagingSearch) >= 2) {
            $packExistingIds = collect($this->packagingLines)->pluck('ingredient_id')->filter()->toArray();
            $packagingSearchResults = Ingredient::with(['baseUom', 'recipeUom', 'uomConversions'])
                ->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->packagingSearch . '%')
                      ->orWhere('code', 'like', '%' . $this->packagingSearch . '%');
                })
                ->when($packExistingIds, fn ($q) => $q->whereNotIn('id', $packExistingIds))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->limit(10)
                ->get();
        }

        // Live cost calculations
        [$lineCosts, $totalCost, $lineTaxes, $totalTax] = $this->computeLineCosts($this->lines);
        [$packagingLineCosts, $packagingCost, $packagingLineTaxes, $packagingTax] = $this->computeLineCosts($this->packagingLines);

        $extraCostTotal = collect($this->extraCosts)->sum(function ($c) use ($totalCost) {
            if (($c['type'] ?? 'value') === 'percent') {
                return $totalCost * (floatval($c['amount'] ?? 0) / 100);
            }
            return floatval($c['amount'] ?? 0);
        });
        $grandCost      = $totalCost + $packagingCost + $extraCostTotal;

        $yieldQty     = max(floatval($this->yield_quantity), 0.0001);
        $sellingPrice = floatval($this->selling_price);

        $costPerServing  = $grandCost / $yieldQty;
        $foodCostPct     = $sellingPrice > 0 ? ($grandCost / $sellingPrice) * 100 : null;
        $grossProfit     = $sellingPrice > 0 ? $sellingPrice - $grandCost : null;
        $grossProfitPct  = $sellingPrice > 0 ? (($sellingPrice - $grandCost) / $sellingPrice) * 100 : null;

        $totalTaxWithPackaging = $totalTax + $packagingTax;
        $grandCostWithTax    = $grandCost + $totalTaxWithPackaging;
        $costPerServingWithTax = $grandCostWithTax / $yieldQty;
        $foodCostPctWithTax  = $sellingPrice > 0 ? ($grandCostWithTax / $sellingPrice) * 100 : null;

        $pageTitle = $this->recipeId
            ? 'Edit: ' . ($this->name ?: 'Recipe')
            : 'New Recipe';

        // Per-class food cost calculations
        $classCostData = [];
        foreach ($priceClasses as $pc) {
            $sp = floatval($this->classPrices[$pc->id] ?? 0);
            $classCostData[$pc->id] = [
                'selling_price'  => $sp,
                'food_cost_pct'  => $sp > 0 ? ($costPerServing / $sp) * 100 : null,
                'gross_profit'   => $sp > 0 ? $sp - $costPerServing : null,
            ];
        }

        return view('livewire.recipes.form', compact(
            'uoms', 'recipeCategories', 'categories', 'departments', 'outlets', 'outletGroups', 'centralKitchenOutletIds', 'searchResults', 'packagingSearchResults', 'lineCosts', 'totalCost',
            'packagingLineCosts', 'packagingCost', 'packagingTax',
            'extraCostTotal', 'grandCost', 'costPerServing', 'foodCostPct', 'grossProfit', 'grossProfitPct',
            'lineTaxes', 'totalTax', 'totalTaxWithPackaging', 'grandCostWithTax', 'costPerServingWithTax', 'foodCostPctWithTax',
            'priceClasses', 'classCostData'
        ))->layout('layouts.app', ['title' => $pageTitle]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function computeLineCosts(?array $lines = null): array
    {
        $lines ??= $this->lines;
        if (empty($lines)) return [[], 0.0, [], 0.0];

        $ingredientIds = collect($lines)->pluck('ingredient_id')->filter()->unique()->values();
        $uomIds        = collect($lines)->pluck('uom_id')->filter()->unique()->values();

        $ingredientsMap = $ingredientIds->isNotEmpty()
            ? Ingredient::with(['baseUom', 'uomConversions', 'taxRate'])
                ->whereIn('id', $ingredientIds)->get()->keyBy('id')
            : collect();

        $uomsMap = $uomIds->isNotEmpty()
            ? UnitOfMeasure::whereIn('id', $uomIds)->get()->keyBy('id')
            : collect();

        $company = Auth::user()?->company;

        $uomService = app(UomService::class);
        $lineCosts  = [];
        $lineTaxes  = [];
        $totalCost  = 0.0;
        $totalTax   = 0.0;

        foreach ($lines as $line) {
            $ingredient = $ingredientsMap->get($line['ingredient_id'] ?? 0);
            $uom        = $uomsMap->get($line['uom_id'] ?? 0);
            $qty        = floatval($line['quantity'] ?? 0);

            if ($ingredient && $uom && $qty > 0) {
                $costPerUom  = $uomService->convertCost($ingredient, $uom);
                $wasteFactor = 1 + (floatval($line['waste_percentage'] ?? 0) / 100);
                $lineCost    = $costPerUom * $wasteFactor * $qty;
                $lineCosts[] = $lineCost;
                $totalCost  += $lineCost;

                $taxRate = $ingredient->effectiveTaxRate($company);
                $taxPct  = $taxRate ? floatval($taxRate->rate) : 0;
                $lineTax = $lineCost * ($taxPct / 100);
                $lineTaxes[] = $lineTax;
                $totalTax   += $lineTax;
            } else {
                $lineCosts[] = null;
                $lineTaxes[] = null;
            }
        }

        return [$lineCosts, $totalCost, $lineTaxes, $totalTax];
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
