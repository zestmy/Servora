<?php

namespace App\Livewire\Ingredients;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientUomConversion;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination, WithFileUploads;

    // Filters
    public string $search = '';
    public string $categoryFilter = '';
    public string $statusFilter = 'all';
    public string $supplierFilter = '';
    public int    $perPage = 100;

    // Bulk selection
    public array $selectedIds = [];
    public bool  $selectAll = false;

    // Modal state
    public bool $showModal = false;
    public ?int $editingId = null;

    // Form fields
    public string $name = '';
    public string $code = '';
    public ?int   $ingredient_category_id = null;
    public ?int   $base_uom_id = null;
    public ?int   $recipe_uom_id = null;
    public string $purchase_price = '0';
    public string $pack_size = '1';
    public string $yield_percent = '100';
    public bool   $is_active = true;
    public string $remark = '';

    // Tax class
    public ?int   $tax_rate_id = null;

    // Default supplier (main form)
    public ?int   $supplier_id = null;

    // Outlet visibility
    public bool   $allOutletsVisible = true;
    public array  $ingredientOutletIds = [];

    // UOM conversions
    public array $conversions = [];

    // Additional supplier links (secondary suppliers)
    public array $supplierLinks = [];

    // Import
    public $importFile = null;
    public bool $showImportModal = false;
    public array $importResults = [];

    // Category management
    public bool $showCategoryModal = false;
    public ?int $editingCategoryId = null;
    public string $catName = '';
    public string $catColor = '#6366f1';
    public string $catSortOrder = '0';
    public bool $catIsActive = true;

    protected function rules(): array
    {
        return [
            'name'                          => 'required|string|max:255',
            'code'                          => 'nullable|string|max:50',
            'ingredient_category_id'        => 'nullable|exists:ingredient_categories,id',
            'base_uom_id'                   => 'required|exists:units_of_measure,id',
            'recipe_uom_id'                 => 'required|exists:units_of_measure,id',
            'purchase_price'                => 'required|numeric|min:0',
            'pack_size'                     => 'required|numeric|min:0.0001',
            'yield_percent'                 => 'required|numeric|min:0.01|max:100',
            'tax_rate_id'                   => 'nullable|exists:tax_rates,id',
            'supplier_id'                   => 'nullable|exists:suppliers,id',
            'conversions.*.from_uom_id'       => 'required|exists:units_of_measure,id',
            'conversions.*.to_uom_id'         => 'required|exists:units_of_measure,id',
            'conversions.*.factor'            => 'required|numeric|min:0.000001',
            'supplierLinks.*.supplier_id'     => 'required|exists:suppliers,id',
            'supplierLinks.*.supplier_sku'    => 'nullable|string|max:100',
            'supplierLinks.*.last_cost'       => 'nullable|numeric|min:0',
            'supplierLinks.*.uom_id'          => 'required|exists:units_of_measure,id',
            'supplierLinks.*.pack_size'        => 'required|numeric|min:0.0001',
        ];
    }

    protected function messages(): array
    {
        return [
            'base_uom_id.required'               => 'Base UOM is required.',
            'recipe_uom_id.required'             => 'Recipe UOM is required.',
            'yield_percent.min'                  => 'Yield must be greater than 0%.',
            'yield_percent.max'                  => 'Yield cannot exceed 100%.',
            'conversions.*.from_uom_id.required' => 'From UOM is required.',
            'conversions.*.to_uom_id.required'   => 'To UOM is required.',
            'conversions.*.factor.required'      => 'Factor is required.',
            'conversions.*.factor.min'              => 'Factor must be greater than zero.',
            'supplierLinks.*.supplier_id.required'  => 'Select a supplier.',
            'supplierLinks.*.uom_id.required'       => 'Select a UOM for the supplier price.',
            'supplierLinks.*.pack_size.required'    => 'Pack size is required.',
            'supplierLinks.*.pack_size.min'         => 'Pack size must be greater than zero.',
        ];
    }

    public function updatedSearch(): void           { $this->resetPage(); $this->clearSelection(); }
    public function updatedCategoryFilter(): void   { $this->resetPage(); $this->clearSelection(); }
    public function updatedStatusFilter(): void     { $this->resetPage(); $this->clearSelection(); }
    public function updatedPerPage(): void          { $this->resetPage(); $this->clearSelection(); }

    public function updatedSelectAll(bool $value): void
    {
        if ($value) {
            // Select all IDs on current page
            $this->selectedIds = $this->getPageIngredientIds();
        } else {
            $this->selectedIds = [];
        }
    }

    public function updatedSelectedIds(): void
    {
        $this->selectAll = count($this->selectedIds) > 0
            && count($this->selectedIds) === count($this->getPageIngredientIds());
    }

    public function openCreate(): void
    {
        if (! $this->assertUnlocked()) return;
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $ingredient = Ingredient::with(['uomConversions', 'suppliers'])->findOrFail($id);

        $this->editingId                = $ingredient->id;
        $this->name                     = $ingredient->name;
        $this->code                     = $ingredient->code ?? '';
        $this->ingredient_category_id   = $ingredient->ingredient_category_id;
        $this->base_uom_id              = $ingredient->base_uom_id;
        $this->recipe_uom_id            = $ingredient->recipe_uom_id;
        $this->purchase_price           = (string) $ingredient->purchase_price;
        $this->pack_size                = (string) ($ingredient->pack_size ?: '1');
        $this->yield_percent            = (string) $ingredient->yield_percent;
        $this->is_active                = $ingredient->is_active;
        $this->remark                   = $ingredient->remark ?? '';
        $this->tax_rate_id              = $ingredient->tax_rate_id;

        $this->conversions = $ingredient->uomConversions
            ->map(fn ($c) => [
                'id'          => $c->id,
                'from_uom_id' => $c->from_uom_id,
                'to_uom_id'   => $c->to_uom_id,
                'factor'      => (string) $c->factor,
            ])
            ->toArray();

        // Split suppliers: preferred/first = default, rest = additional
        $allSuppliers = $ingredient->suppliers->sortByDesc(fn ($s) => $s->pivot->is_preferred);
        $defaultSupplier = $allSuppliers->first();

        $this->supplier_id = $defaultSupplier?->id;

        $this->supplierLinks = $allSuppliers->skip(1)
            ->map(fn ($s) => [
                'supplier_id'  => $s->id,
                'supplier_sku' => $s->pivot->supplier_sku ?? '',
                'last_cost'    => (string) ($s->pivot->last_cost ?? ''),
                'uom_id'       => $s->pivot->uom_id,
                'pack_size'    => (string) ($s->pivot->pack_size ?? '1'),
                'is_preferred' => false,
            ])
            ->values()
            ->toArray();

        // Load outlet visibility
        $assignedOutletIds = $ingredient->outlets()->pluck('outlets.id')->toArray();
        $this->ingredientOutletIds = array_map('strval', $assignedOutletIds);
        $this->allOutletsVisible = empty($assignedOutletIds);

        $this->showModal = true;
    }

    /** True when the company admin has locked the ingredients list for this user. */
    public function getLockedProperty(): bool
    {
        $user = Auth::user();
        return (bool) ($user?->company?->ingredients_locked)
            && ! $user?->canBypassLock();
    }

    private function assertUnlocked(): bool
    {
        if ($this->locked) {
            session()->flash('error', 'Ingredients are locked. Ask a company admin to unlock in Settings → Company Details.');
            return false;
        }
        return true;
    }

    public function save(): void
    {
        if (! $this->assertUnlocked()) return;
        $this->validate();

        $purchasePrice = floatval($this->purchase_price);
        $packSize      = max(floatval($this->pack_size), 0.0001);
        $yieldPercent  = floatval($this->yield_percent);
        $baseCost      = $purchasePrice / $packSize;
        $effectiveCost = $yieldPercent > 0 ? ($baseCost / ($yieldPercent / 100)) : $baseCost;

        $data = [
            'name'                   => strtoupper($this->name),
            'code'                   => $this->code ?: null,
            'ingredient_category_id' => $this->ingredient_category_id,
            'base_uom_id'            => $this->base_uom_id,
            'recipe_uom_id'          => $this->recipe_uom_id,
            'purchase_price'         => $purchasePrice,
            'pack_size'              => $packSize,
            'yield_percent'          => $yieldPercent,
            'current_cost'           => $effectiveCost,
            'tax_rate_id'            => $this->tax_rate_id,
            'is_active'              => $this->is_active,
            'remark'                 => $this->remark ?: null,
        ];

        if ($this->editingId) {
            $ingredient = Ingredient::findOrFail($this->editingId);
            $ingredient->update($data);
            $this->saveConversions($ingredient);
            $this->saveSupplierLinks($ingredient);
            session()->flash('success', 'Ingredient updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $ingredient = Ingredient::create($data);
            $this->saveSupplierLinks($ingredient);
            session()->flash('success', 'Ingredient created.');
        }

        // Sync outlet visibility
        if ($this->allOutletsVisible) {
            $ingredient->outlets()->detach(); // no assignments = visible everywhere
        } else {
            $ingredient->outlets()->sync(array_map('intval', $this->ingredientOutletIds));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        if (! $this->assertUnlocked()) return;
        Ingredient::findOrFail($id)->delete();
        session()->flash('success', 'Ingredient deleted.');
    }

    public function bulkDelete(): void
    {
        if (! $this->assertUnlocked()) return;
        $count = count($this->selectedIds);
        if ($count === 0) return;

        Ingredient::whereIn('id', $this->selectedIds)->delete();
        $this->clearSelection();
        session()->flash('success', "{$count} ingredient(s) deleted.");
    }

    public function toggleActive(int $id): void
    {
        if (! $this->assertUnlocked()) return;
        $ingredient = Ingredient::findOrFail($id);
        $ingredient->update(['is_active' => ! $ingredient->is_active]);
    }

    public function addConversionRow(): void
    {
        $this->conversions[] = [
            'id'          => null,
            'from_uom_id' => $this->base_uom_id,
            'to_uom_id'   => $this->recipe_uom_id ?? $this->base_uom_id,
            'factor'      => '',
        ];
    }

    public function removeConversionRow(int $idx): void
    {
        unset($this->conversions[$idx]);
        $this->conversions = array_values($this->conversions);
    }

    public function addSupplierRow(): void
    {
        $this->supplierLinks[] = [
            'supplier_id'  => null,
            'supplier_sku' => '',
            'last_cost'    => $this->purchase_price,
            'uom_id'       => $this->base_uom_id,
            'pack_size'    => $this->pack_size,
            'is_preferred' => false,
        ];
    }

    public function removeSupplierRow(int $idx): void
    {
        unset($this->supplierLinks[$idx]);
        $this->supplierLinks = array_values($this->supplierLinks);
    }

    public function openImport(): void
    {
        $this->importFile = null;
        $this->importResults = [];
        $this->showImportModal = true;
    }

    public function closeImport(): void
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importResults = [];
    }

    public function processImport(): void
    {
        if (! $this->assertUnlocked()) return;
        $this->validate([
            'importFile' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $path = $this->importFile->getRealPath();
        $handle = fopen($path, 'r');

        if (! $handle) {
            session()->flash('error', 'Could not read the uploaded file.');
            return;
        }

        // Skip BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headerRow = fgetcsv($handle);
        if (! $headerRow) {
            fclose($handle);
            session()->flash('error', 'Empty file or invalid format.');
            return;
        }

        // Normalize headers
        $headerRow = array_map(fn ($h) => strtolower(trim($h)), $headerRow);
        $idIdx    = array_search('id', $headerRow);
        $nameIdx  = array_search('name', $headerRow);
        $codeIdx  = array_search('code', $headerRow);
        $priceIdx = array_search('purchase price', $headerRow);
        $yieldIdx = array_search('yield %', $headerRow);
        $activeIdx = array_search('is active', $headerRow);
        $remarkIdx = array_search('remark', $headerRow);

        if ($idIdx === false || $nameIdx === false) {
            fclose($handle);
            session()->flash('error', 'CSV must have at least "ID" and "Name" columns.');
            return;
        }

        $updated = 0;
        $skipped = 0;
        $errors = [];
        $row = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            $id = trim($data[$idIdx] ?? '');

            if (! $id || ! is_numeric($id)) {
                $skipped++;
                continue;
            }

            $ingredient = Ingredient::find((int) $id);
            if (! $ingredient) {
                $errors[] = "Row {$row}: Ingredient ID {$id} not found.";
                $skipped++;
                continue;
            }

            $changes = [];

            if ($nameIdx !== false && isset($data[$nameIdx]) && trim($data[$nameIdx]) !== '') {
                $changes['name'] = strtoupper(trim($data[$nameIdx]));
            }

            if ($codeIdx !== false && isset($data[$codeIdx])) {
                $changes['code'] = trim($data[$codeIdx]) ?: null;
            }

            if ($priceIdx !== false && isset($data[$priceIdx]) && is_numeric(trim($data[$priceIdx]))) {
                $changes['purchase_price'] = floatval(trim($data[$priceIdx]));
            }

            if ($yieldIdx !== false && isset($data[$yieldIdx]) && is_numeric(trim($data[$yieldIdx]))) {
                $yp = floatval(trim($data[$yieldIdx]));
                if ($yp > 0 && $yp <= 100) {
                    $changes['yield_percent'] = $yp;
                }
            }

            if ($activeIdx !== false && isset($data[$activeIdx])) {
                $val = strtolower(trim($data[$activeIdx]));
                if (in_array($val, ['yes', 'no', '1', '0', 'true', 'false'])) {
                    $changes['is_active'] = in_array($val, ['yes', '1', 'true']);
                }
            }

            if ($remarkIdx !== false && isset($data[$remarkIdx])) {
                $changes['remark'] = trim($data[$remarkIdx]) ?: null;
            }

            if (! empty($changes)) {
                // Recalculate effective cost if price, pack_size, or yield changed
                if (isset($changes['purchase_price']) || isset($changes['yield_percent']) || isset($changes['pack_size'])) {
                    $pp = $changes['purchase_price'] ?? (float) $ingredient->purchase_price;
                    $ps = $changes['pack_size'] ?? (float) ($ingredient->pack_size ?: 1);
                    $yp = $changes['yield_percent'] ?? (float) $ingredient->yield_percent;
                    $bc = $pp / max($ps, 0.0001);
                    $changes['current_cost'] = $yp > 0 ? ($bc / ($yp / 100)) : $bc;
                }
                $ingredient->update($changes);
                $updated++;
            } else {
                $skipped++;
            }
        }

        fclose($handle);

        $this->importResults = [
            'updated' => $updated,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];

        if ($updated > 0) {
            session()->flash('success', "{$updated} ingredient(s) updated successfully.");
        }
    }

    // ── Category Management ──

    public function openCreateCategory(): void
    {
        $this->resetCategoryForm();
        $this->showCategoryModal = true;
    }

    public function openEditCategory(int $id): void
    {
        $cat = IngredientCategory::findOrFail($id);
        $this->editingCategoryId = $cat->id;
        $this->catName = $cat->name;
        $this->catColor = $cat->color;
        $this->catSortOrder = (string) $cat->sort_order;
        $this->catIsActive = $cat->is_active;
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        $this->validate([
            'catName' => 'required|string|max:100',
            'catColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'catSortOrder' => 'required|integer|min:0|max:9999',
        ]);

        $data = [
            'name' => strtoupper($this->catName),
            'color' => $this->catColor,
            'sort_order' => (int) $this->catSortOrder,
            'is_active' => $this->catIsActive,
            'parent_id' => null,
        ];

        if ($this->editingCategoryId) {
            IngredientCategory::findOrFail($this->editingCategoryId)->update($data);
            session()->flash('success', 'Category updated.');
        } else {
            $data['company_id'] = Auth::user()->company_id;
            IngredientCategory::create($data);
            session()->flash('success', 'Category created.');
        }

        $this->closeCategoryModal();
    }

    public function deleteCategory(int $id): void
    {
        $cat = IngredientCategory::withCount('ingredients')->findOrFail($id);

        if ($cat->ingredients_count > 0) {
            session()->flash('error', "Cannot delete \"{$cat->name}\" — {$cat->ingredients_count} ingredient(s) assigned.");
            return;
        }

        $cat->delete();
        session()->flash('success', 'Category deleted.');
    }

    public function closeCategoryModal(): void
    {
        $this->showCategoryModal = false;
        $this->resetCategoryForm();
    }

    private function resetCategoryForm(): void
    {
        $this->editingCategoryId = null;
        $this->catName = '';
        $this->catColor = '#6366f1';
        $this->catSortOrder = '0';
        $this->catIsActive = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = Ingredient::with(['baseUom', 'recipeUom', 'uomConversions', 'suppliers', 'ingredientCategory.parent', 'taxRate']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter) {
            $cat = IngredientCategory::with('children')->find((int) $this->categoryFilter);
            if ($cat) {
                $ids = $cat->children->isNotEmpty()
                    ? $cat->children->pluck('id')->push($cat->id)->toArray()
                    : [$cat->id];
                $query->whereIn('ingredient_category_id', $ids);
            }
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        if ($this->supplierFilter) {
            if ($this->supplierFilter === 'none') {
                $query->whereDoesntHave('suppliers');
            } else {
                $query->whereHas('suppliers', fn ($q) => $q->where('suppliers.id', (int) $this->supplierFilter));
            }
        }

        // Filter by active outlet visibility (assigned to outlet OR not assigned to any = visible everywhere)
        $outletId = Auth::user()->activeOutletId();
        if ($outletId) {
            $query->where(function ($q) use ($outletId) {
                $q->whereHas('outlets', fn ($sub) => $sub->where('outlets.id', $outletId))
                  ->orWhereDoesntHave('outlets');
            });
        }

        $ingredients = $query->orderBy('name')->paginate($this->perPage);

        $uoms = UnitOfMeasure::orderBy('name')->get();

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        $categories = IngredientCategory::roots()
            ->with('children')
            ->withCount('ingredients')
            ->active()
            ->ordered()
            ->get();

        // Cost chain computed values for modal display
        $pp            = floatval($this->purchase_price);
        $ps            = max(floatval($this->pack_size), 0.0001);
        $yp            = floatval($this->yield_percent);
        $baseCost      = $pp / $ps;
        $effectiveCost = ($yp > 0) ? ($baseCost / ($yp / 100)) : $baseCost;

        // Recipe cost per recipe UOM (searches loaded conversions)
        $recipeCost    = null;
        $baseUom       = $this->base_uom_id ? $uoms->firstWhere('id', $this->base_uom_id) : null;
        $recipeUom     = $this->recipe_uom_id ? $uoms->firstWhere('id', $this->recipe_uom_id) : null;
        $baseUomAbbr   = $baseUom?->abbreviation;
        $recipeUomAbbr = $recipeUom?->abbreviation;

        if ($this->base_uom_id && $this->recipe_uom_id) {
            if ($this->base_uom_id == $this->recipe_uom_id) {
                $recipeCost = $effectiveCost;
            } else {
                // Check ingredient-specific conversions first
                foreach ($this->conversions as $c) {
                    $fromId = (int) ($c['from_uom_id'] ?? 0);
                    $toId   = (int) ($c['to_uom_id'] ?? 0);
                    $factor = floatval($c['factor'] ?? 0);

                    if ($factor <= 0) continue;

                    if ($fromId === (int) $this->base_uom_id && $toId === (int) $this->recipe_uom_id) {
                        $recipeCost = $effectiveCost / $factor;
                        break;
                    }

                    if ($fromId === (int) $this->recipe_uom_id && $toId === (int) $this->base_uom_id) {
                        $recipeCost = $effectiveCost * $factor;
                        break;
                    }
                }

                // Fall back to standard base_unit_factor (kg↔g, L↔mL, etc.)
                if ($recipeCost === null && $baseUom && $recipeUom
                    && $baseUom->base_unit_factor && $recipeUom->base_unit_factor
                    && $baseUom->type === $recipeUom->type) {
                    $factor = (float) $recipeUom->base_unit_factor / (float) $baseUom->base_unit_factor;
                    $recipeCost = $effectiveCost * $factor;
                }
            }
        }

        $outlets = \App\Models\Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)->orderBy('name')->get();

        $taxRates = \App\Models\TaxRate::active()->orderBy('name')->get();

        return view('livewire.ingredients.index', compact(
            'ingredients', 'uoms', 'suppliers', 'categories', 'outlets', 'taxRates',
            'baseCost', 'effectiveCost', 'recipeCost', 'baseUomAbbr', 'recipeUomAbbr'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Ingredients']);
    }

    private function resetForm(): void
    {
        $this->editingId              = null;
        $this->name                   = '';
        $this->code                   = '';
        $this->ingredient_category_id = null;
        $this->base_uom_id            = null;
        $this->recipe_uom_id          = null;
        $this->purchase_price         = '0';
        $this->pack_size              = '1';
        $this->yield_percent          = '100';
        $this->is_active              = true;
        $this->remark                 = '';
        $this->tax_rate_id            = null;
        $this->supplier_id            = null;
        $this->conversions            = [];
        $this->supplierLinks          = [];
        $this->allOutletsVisible      = true;
        $this->ingredientOutletIds    = [];
        $this->resetValidation();
    }

    private function saveSupplierLinks(Ingredient $ingredient): void
    {
        $ingredient->suppliers()->detach();

        // Save default supplier (from main form) as preferred
        if ($this->supplier_id) {
            $ingredient->suppliers()->attach($this->supplier_id, [
                'supplier_sku' => null,
                'last_cost'    => floatval($this->purchase_price) > 0 ? $this->purchase_price : null,
                'uom_id'       => $this->base_uom_id,
                'pack_size'    => max(floatval($this->pack_size), 0.0001),
                'is_preferred' => true,
            ]);
        }

        // Save additional suppliers
        foreach ($this->supplierLinks as $link) {
            // Skip if same as default supplier
            if ((int) $link['supplier_id'] === (int) $this->supplier_id) continue;

            $ingredient->suppliers()->attach($link['supplier_id'], [
                'supplier_sku' => $link['supplier_sku'] ?: null,
                'last_cost'    => $link['last_cost'] !== '' ? $link['last_cost'] : null,
                'uom_id'       => $link['uom_id'],
                'pack_size'    => floatval($link['pack_size'] ?? 1) ?: 1,
                'is_preferred' => false,
            ]);
        }
    }

    private function clearSelection(): void
    {
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    private function getPageIngredientIds(): array
    {
        $query = Ingredient::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter) {
            $cat = IngredientCategory::with('children')->find((int) $this->categoryFilter);
            if ($cat) {
                $ids = $cat->children->isNotEmpty()
                    ? $cat->children->pluck('id')->push($cat->id)->toArray()
                    : [$cat->id];
                $query->whereIn('ingredient_category_id', $ids);
            }
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->orderBy('name')->paginate($this->perPage)->pluck('id')->toArray();
    }

    private function saveConversions(Ingredient $ingredient): void
    {
        $ingredient->uomConversions()->delete();

        foreach ($this->conversions as $row) {
            $ingredient->uomConversions()->create([
                'from_uom_id' => $row['from_uom_id'],
                'to_uom_id'   => $row['to_uom_id'],
                'factor'      => $row['factor'],
            ]);
        }
    }
}
