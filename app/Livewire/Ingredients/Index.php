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
    public string $yield_percent = '100';
    public bool   $is_active = true;

    // UOM conversions
    public array $conversions = [];

    // Supplier links
    public array $supplierLinks = [];

    // Import
    public $importFile = null;
    public bool $showImportModal = false;
    public array $importResults = [];

    protected function rules(): array
    {
        return [
            'name'                          => 'required|string|max:255',
            'code'                          => 'nullable|string|max:50',
            'ingredient_category_id'        => 'nullable|exists:ingredient_categories,id',
            'base_uom_id'                   => 'required|exists:units_of_measure,id',
            'recipe_uom_id'                 => 'required|exists:units_of_measure,id',
            'purchase_price'                => 'required|numeric|min:0',
            'yield_percent'                 => 'required|numeric|min:0.01|max:100',
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

    public function updatedSearch(): void           { $this->resetPage(); }
    public function updatedCategoryFilter(): void   { $this->resetPage(); }
    public function updatedStatusFilter(): void     { $this->resetPage(); }

    public function openCreate(): void
    {
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
        $this->yield_percent            = (string) $ingredient->yield_percent;
        $this->is_active                = $ingredient->is_active;

        $this->conversions = $ingredient->uomConversions
            ->map(fn ($c) => [
                'id'          => $c->id,
                'from_uom_id' => $c->from_uom_id,
                'to_uom_id'   => $c->to_uom_id,
                'factor'      => (string) $c->factor,
            ])
            ->toArray();

        $this->supplierLinks = $ingredient->suppliers
            ->map(fn ($s) => [
                'supplier_id'  => $s->id,
                'supplier_sku' => $s->pivot->supplier_sku ?? '',
                'last_cost'    => (string) ($s->pivot->last_cost ?? ''),
                'uom_id'       => $s->pivot->uom_id,
                'pack_size'    => (string) ($s->pivot->pack_size ?? '1'),
                'is_preferred' => (bool) $s->pivot->is_preferred,
            ])
            ->toArray();

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $purchasePrice = floatval($this->purchase_price);
        $yieldPercent  = floatval($this->yield_percent);
        $effectiveCost = $yieldPercent > 0 ? ($purchasePrice / ($yieldPercent / 100)) : $purchasePrice;

        $data = [
            'name'                   => strtoupper($this->name),
            'code'                   => $this->code ?: null,
            'ingredient_category_id' => $this->ingredient_category_id,
            'base_uom_id'            => $this->base_uom_id,
            'recipe_uom_id'          => $this->recipe_uom_id,
            'purchase_price'         => $purchasePrice,
            'yield_percent'          => $yieldPercent,
            'current_cost'           => $effectiveCost,
            'is_active'              => $this->is_active,
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
            session()->flash('success', 'Ingredient created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        Ingredient::findOrFail($id)->delete();
        session()->flash('success', 'Ingredient deleted.');
    }

    public function toggleActive(int $id): void
    {
        $ingredient = Ingredient::findOrFail($id);
        $ingredient->update(['is_active' => ! $ingredient->is_active]);
    }

    public function addConversionRow(): void
    {
        $this->conversions[] = [
            'id'          => null,
            'from_uom_id' => null,
            'to_uom_id'   => null,
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
            'last_cost'    => '',
            'uom_id'       => null,
            'pack_size'    => '1',
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

            if (! empty($changes)) {
                // Recalculate effective cost if price or yield changed
                if (isset($changes['purchase_price']) || isset($changes['yield_percent'])) {
                    $pp = $changes['purchase_price'] ?? (float) $ingredient->purchase_price;
                    $yp = $changes['yield_percent'] ?? (float) $ingredient->yield_percent;
                    $changes['current_cost'] = $yp > 0 ? ($pp / ($yp / 100)) : $pp;
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

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        $query = Ingredient::with(['baseUom', 'recipeUom', 'uomConversions', 'suppliers', 'ingredientCategory.parent']);

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

        $ingredients = $query->orderBy('name')->paginate(15);

        $uoms = UnitOfMeasure::orderBy('name')->get();

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        $categories = IngredientCategory::roots()
            ->with('children')
            ->active()
            ->ordered()
            ->get();

        // Cost chain computed values for modal display
        $pp            = floatval($this->purchase_price);
        $yp            = floatval($this->yield_percent);
        $effectiveCost = ($yp > 0) ? ($pp / ($yp / 100)) : $pp;

        // Recipe cost per recipe UOM (searches loaded conversions)
        $recipeCost    = null;
        $baseUomAbbr   = null;
        $recipeUomAbbr = null;

        if ($this->base_uom_id) {
            $baseUom     = $uoms->firstWhere('id', $this->base_uom_id);
            $baseUomAbbr = $baseUom?->abbreviation;
        }
        if ($this->recipe_uom_id) {
            $recipeUom     = $uoms->firstWhere('id', $this->recipe_uom_id);
            $recipeUomAbbr = $recipeUom?->abbreviation;
        }

        if ($this->base_uom_id && $this->recipe_uom_id) {
            if ($this->base_uom_id == $this->recipe_uom_id) {
                $recipeCost = $effectiveCost;
            } else {
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
            }
        }

        return view('livewire.ingredients.index', compact(
            'ingredients', 'uoms', 'suppliers', 'categories',
            'effectiveCost', 'recipeCost', 'baseUomAbbr', 'recipeUomAbbr'
        ))->layout('layouts.app', ['title' => 'Ingredients']);
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
        $this->yield_percent          = '100';
        $this->is_active              = true;
        $this->conversions            = [];
        $this->supplierLinks          = [];
        $this->resetValidation();
    }

    private function saveSupplierLinks(Ingredient $ingredient): void
    {
        $ingredient->suppliers()->detach();

        foreach ($this->supplierLinks as $link) {
            $ingredient->suppliers()->attach($link['supplier_id'], [
                'supplier_sku' => $link['supplier_sku'] ?: null,
                'last_cost'    => $link['last_cost'] !== '' ? $link['last_cost'] : null,
                'uom_id'       => $link['uom_id'],
                'pack_size'    => floatval($link['pack_size'] ?? 1) ?: 1,
                'is_preferred' => (bool) ($link['is_preferred'] ?? false),
            ]);
        }
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
