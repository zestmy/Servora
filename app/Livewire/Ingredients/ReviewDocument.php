<?php

namespace App\Livewire\Ingredients;

use App\Models\Ingredient;
use App\Models\IngredientPriceHistory;
use App\Models\ScannedDocument;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Review + import one ScannedDocument. The document itself was produced by
 * ScanDocument; here the user confirms the supplier, the effective date,
 * matches each extracted line item to an existing ingredient (or creates a
 * new one), then commits.
 */
class ReviewDocument extends Component
{
    public int $documentId;

    // Hydrated from the scanned document on mount
    public array $items = [];
    public int $totalItems = 0;
    public int $matchedCount = 0;
    public int $newCount = 0;

    // Supplier confirmation
    public ?int   $supplierId          = null;
    public string $supplierName        = '';
    public string $detectedSupplierName = '';
    public string $supplierMode        = 'existing'; // existing | new
    public string $newSupplierName     = '';

    public string $effectiveDate = '';

    // Done state (after import)
    public bool $imported        = false;
    public int  $linkedCount     = 0;
    public int  $createdCount    = 0;
    public int  $skippedCount    = 0;
    public int  $priceChangedCount = 0;

    public function mount(int $document): void
    {
        $this->documentId = $document;
        $doc = ScannedDocument::findOrFail($document);

        if ($doc->status === 'imported') {
            $this->imported = true;
        }

        $this->detectedSupplierName = (string) ($doc->supplier_name_detected ?? '');
        $this->supplierId           = $doc->supplier_id;
        $this->effectiveDate        = optional($doc->effective_date ?? $doc->document_date_detected)->toDateString()
            ?? now()->toDateString();

        if ($this->supplierId) {
            $this->supplierName = Supplier::find($this->supplierId)?->name ?? '';
            $this->supplierMode = 'existing';
        } elseif ($this->detectedSupplierName !== '') {
            $this->supplierMode    = 'new';
            $this->newSupplierName = $this->detectedSupplierName;
        }

        $this->buildPreview((array) ($doc->extracted_items ?? []));
    }

    // ── Preview ingredients / UOM matching ──────────────────────────────

    private function buildPreview(array $extracted): void
    {
        $companyId = Auth::user()->company_id;

        $ingredientsByName = Ingredient::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($i) => strtolower(trim($i->name)));

        $existingLinks = $this->supplierId
            ? DB::table('supplier_ingredients')
                ->where('supplier_id', $this->supplierId)
                ->pluck('ingredient_id')
                ->flip()
            : collect();

        $uomsByAbbr = UnitOfMeasure::all()->mapWithKeys(fn ($u) => [strtolower($u->abbreviation) => $u]);
        $uomsByName = UnitOfMeasure::all()->mapWithKeys(fn ($u) => [strtolower($u->name) => $u]);

        $this->items = [];

        foreach ($extracted as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if (! $name) continue;

            $match = $this->matchIngredient($name, $ingredientsByName);

            $uomRaw  = trim((string) ($item['uom'] ?? ''));
            $uomId   = $this->resolveUom($uomRaw, $uomsByAbbr, $uomsByName);
            $price   = floatval($item['price'] ?? 0);
            $code    = trim((string) ($item['code'] ?? '')) ?: null;
            $pack    = floatval($item['pack_size'] ?? 0);
            $recipeUomRaw = trim((string) ($item['recipe_uom'] ?? ''));
            $recipeUomId  = $recipeUomRaw ? $this->resolveUom($recipeUomRaw, $uomsByAbbr, $uomsByName) : null;

            $alreadyLinked = $match && isset($existingLinks[$match['id']]);
            $status = $match ? ($alreadyLinked ? 'already_linked' : 'match') : 'new';

            $this->items[] = [
                'name'           => $name,
                'code'           => $code,
                'uom_raw'        => $uomRaw,
                'uom_id'         => $uomId,
                'recipe_uom_raw' => $recipeUomRaw,
                'recipe_uom_id'  => $recipeUomId,
                'pack_size'      => $pack,
                'price'          => $price,
                'category'       => trim((string) ($item['category'] ?? '')) ?: null,
                'ingredient_id'  => $match['id'] ?? null,
                'matched_name'   => $match['name'] ?? null,
                'confidence'     => $match['confidence'] ?? 0,
                'already_linked' => $alreadyLinked,
                'status'         => $status,
                'action'         => $alreadyLinked ? 'skip' : ($match ? 'link' : 'create'),
            ];
        }

        $this->recalcCounts();
    }

    private function matchIngredient(string $name, $ingredientsByName): ?array
    {
        $key = strtolower(trim($name));
        if (isset($ingredientsByName[$key])) {
            $ing = $ingredientsByName[$key];
            return ['id' => $ing->id, 'name' => $ing->name, 'confidence' => 100];
        }
        $bestScore = 0; $bestMatch = null;
        foreach ($ingredientsByName as $k => $ing) {
            similar_text($key, $k, $pct);
            if ($pct > $bestScore && $pct >= 60) { $bestScore = $pct; $bestMatch = $ing; }
        }
        return $bestMatch ? ['id' => $bestMatch->id, 'name' => $bestMatch->name, 'confidence' => (int) $bestScore] : null;
    }

    private function resolveUom(string $raw, $uomsByAbbr, $uomsByName): ?int
    {
        if (! $raw) return null;
        $key = strtolower(trim($raw));
        if (isset($uomsByAbbr[$key])) return $uomsByAbbr[$key]->id;
        if (isset($uomsByName[$key])) return $uomsByName[$key]->id;

        $aliases = [
            'liter' => 'l', 'litre' => 'l', 'lit' => 'l', 'ltr' => 'l',
            'gram' => 'g', 'gm' => 'g', 'grams' => 'g', 'gms' => 'g',
            'kilogram' => 'kg', 'kilo' => 'kg', 'kgs' => 'kg',
            'milliliter' => 'ml', 'millilitre' => 'ml',
            'piece' => 'pcs', 'pieces' => 'pcs', 'pc' => 'pcs', 'each' => 'pcs', 'ea' => 'pcs',
            'carton' => 'ctn', 'packet' => 'pkt', 'bottle' => 'bottle', 'btl' => 'bottle',
            'pack' => 'pack', 'packs' => 'pack', 'bags' => 'bag', 'boxes' => 'box',
            'tray' => 'tray', 'trays' => 'tray', 'pails' => 'pail', 'tin' => 'can', 'tins' => 'can',
        ];
        $alias = $aliases[$key] ?? null;
        if ($alias && isset($uomsByAbbr[$alias])) return $uomsByAbbr[$alias]->id;
        return null;
    }

    public function fixMatch(int $idx, int $ingredientId): void
    {
        if (! isset($this->items[$idx])) return;
        $ing = Ingredient::find($ingredientId);
        if (! $ing) return;

        $alreadyLinked = $this->supplierId && DB::table('supplier_ingredients')
            ->where('supplier_id', $this->supplierId)
            ->where('ingredient_id', $ingredientId)
            ->exists();

        $this->items[$idx]['ingredient_id']  = $ing->id;
        $this->items[$idx]['matched_name']   = $ing->name;
        $this->items[$idx]['confidence']     = 100;
        $this->items[$idx]['already_linked'] = $alreadyLinked;
        $this->items[$idx]['status']         = $alreadyLinked ? 'already_linked' : 'match';
        $this->items[$idx]['action']         = $alreadyLinked ? 'skip' : 'link';
        $this->recalcCounts();
    }

    public function fixUom(int $idx, int $uomId): void
    {
        if (isset($this->items[$idx])) $this->items[$idx]['uom_id'] = $uomId;
    }

    public function setAction(int $idx, string $action): void
    {
        if (! isset($this->items[$idx])) return;
        if (in_array($action, ['link', 'create', 'skip'])) {
            $this->items[$idx]['action'] = $action;
        }
    }

    private function recalcCounts(): void
    {
        $this->totalItems   = count($this->items);
        $this->matchedCount = collect($this->items)->whereIn('status', ['match', 'already_linked'])->count();
        $this->newCount     = collect($this->items)->where('status', 'new')->count();
    }

    // ── Supplier inline create ─────────────────────────────────────────

    public function createSupplier(): void
    {
        $this->validate([
            'newSupplierName' => 'required|string|max:255',
        ], ['newSupplierName.required' => 'Give the new supplier a name.']);

        $companyId = Auth::user()->company_id;
        $supplier = Supplier::create([
            'company_id' => $companyId,
            'name'       => trim($this->newSupplierName),
            'is_active'  => true,
        ]);

        $this->supplierId      = $supplier->id;
        $this->supplierName    = $supplier->name;
        $this->supplierMode    = 'existing';
        $this->newSupplierName = '';
        session()->flash('success', "Supplier \"{$supplier->name}\" created.");

        $this->refreshExistingLinks();
    }

    private function refreshExistingLinks(): void
    {
        if (! $this->supplierId) return;
        $existing = DB::table('supplier_ingredients')
            ->where('supplier_id', $this->supplierId)
            ->pluck('ingredient_id')
            ->flip();

        foreach ($this->items as $idx => $item) {
            if (! $item['ingredient_id']) continue;
            $linked = isset($existing[$item['ingredient_id']]);
            $this->items[$idx]['already_linked'] = $linked;
            $this->items[$idx]['status']         = $linked ? 'already_linked' : 'match';
            $this->items[$idx]['action']         = $linked ? 'skip' : 'link';
        }
        $this->recalcCounts();
    }

    public function updatedSupplierId(): void
    {
        $this->supplierName = $this->supplierId ? (Supplier::find($this->supplierId)?->name ?? '') : '';
        $this->refreshExistingLinks();
    }

    // ── Import ─────────────────────────────────────────────────────────

    public function import(): void
    {
        $doc = ScannedDocument::findOrFail($this->documentId);
        if ($doc->status === 'imported') {
            $this->imported = true;
            return;
        }

        $user      = Auth::user();
        $companyId = $user->company_id;

        if (! $this->supplierId && $this->supplierMode === 'new' && trim($this->newSupplierName) !== '') {
            $supplier = Supplier::create([
                'company_id' => $companyId,
                'name'       => trim($this->newSupplierName),
                'is_active'  => true,
            ]);
            $this->supplierId   = $supplier->id;
            $this->supplierName = $supplier->name;
        }

        if (! $this->supplierId) {
            session()->flash('error', 'Pick an existing supplier or create a new one before importing.');
            return;
        }

        $effectiveDate = $this->effectiveDate ?: now()->toDateString();

        $linked       = 0;
        $created      = 0;
        $skipped      = 0;
        $priceChanged = 0;

        foreach ($this->items as $item) {
            if ($item['action'] === 'skip') { $skipped++; continue; }

            if ($item['action'] === 'link' && $item['ingredient_id']) {
                $uomId = $item['uom_id'] ?? UnitOfMeasure::first()?->id;
                $pack  = max($item['pack_size'], 0.0001) ?: 1;
                $price = floatval($item['price'] ?? 0);

                $existing = DB::table('supplier_ingredients')
                    ->where('supplier_id', $this->supplierId)
                    ->where('ingredient_id', $item['ingredient_id'])
                    ->first();

                if ($existing) {
                    $old = $existing->last_cost !== null ? (float) $existing->last_cost : null;
                    $changed = $price > 0 && $old !== null && abs($price - $old) > 0.0001;

                    DB::table('supplier_ingredients')->where('id', $existing->id)->update([
                        'supplier_sku' => $item['code'] ?: $existing->supplier_sku,
                        'last_cost'    => $price > 0 ? $price : $existing->last_cost,
                        'uom_id'       => $uomId,
                        'pack_size'    => $pack,
                        'updated_at'   => now(),
                    ]);

                    if ($price > 0) {
                        IngredientPriceHistory::create([
                            'ingredient_id'  => $item['ingredient_id'],
                            'supplier_id'    => $this->supplierId,
                            'cost'           => $price,
                            'uom_id'         => $uomId,
                            'effective_date' => $effectiveDate,
                            'source'         => 'price_watcher_import',
                        ]);
                    }
                    if ($changed) $priceChanged++;
                } else {
                    DB::table('supplier_ingredients')->insert([
                        'supplier_id'   => $this->supplierId,
                        'ingredient_id' => $item['ingredient_id'],
                        'supplier_sku'  => $item['code'],
                        'last_cost'     => $price > 0 ? $price : null,
                        'uom_id'        => $uomId,
                        'pack_size'     => $pack,
                        'is_preferred'  => false,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                    if ($price > 0) {
                        IngredientPriceHistory::create([
                            'ingredient_id'  => $item['ingredient_id'],
                            'supplier_id'    => $this->supplierId,
                            'cost'           => $price,
                            'uom_id'         => $uomId,
                            'effective_date' => $effectiveDate,
                            'source'         => 'price_watcher_import',
                        ]);
                    }
                }
                $linked++;
            } elseif ($item['action'] === 'create') {
                DB::transaction(function () use ($item, $companyId, $effectiveDate, &$created) {
                    $baseUomId   = $item['uom_id'] ?? UnitOfMeasure::first()?->id;
                    $recipeUomId = $item['recipe_uom_id'] ?? $baseUomId;
                    $pack        = max($item['pack_size'], 0.0001) ?: 1;
                    $price       = floatval($item['price'] ?? 0);
                    $currentCost = $pack > 0 && $price > 0 ? round($price / $pack, 4) : 0;

                    $ing = Ingredient::create([
                        'company_id'     => $companyId,
                        'name'           => $item['name'],
                        'code'           => $item['code'],
                        'base_uom_id'    => $baseUomId,
                        'recipe_uom_id'  => $recipeUomId,
                        'purchase_price' => $price,
                        'current_cost'   => $currentCost,
                        'yield_percent'  => 100,
                        'is_active'      => true,
                        'is_prep'        => false,
                    ]);

                    $ing->suppliers()->attach($this->supplierId, [
                        'supplier_sku' => $item['code'],
                        'last_cost'    => $price > 0 ? $price : null,
                        'uom_id'       => $baseUomId,
                        'pack_size'    => $pack,
                        'is_preferred' => true,
                    ]);

                    if ($price > 0) {
                        IngredientPriceHistory::create([
                            'ingredient_id'  => $ing->id,
                            'supplier_id'    => $this->supplierId,
                            'cost'           => $price,
                            'uom_id'         => $baseUomId,
                            'effective_date' => $effectiveDate,
                            'source'         => 'price_watcher_import',
                        ]);
                    }
                    $created++;
                });
            }
        }

        $doc->update([
            'status'         => 'imported',
            'supplier_id'    => $this->supplierId,
            'effective_date' => $effectiveDate,
            'imported_at'    => now(),
        ]);

        $this->linkedCount       = $linked;
        $this->createdCount      = $created;
        $this->skippedCount      = $skipped;
        $this->priceChangedCount = $priceChanged;
        $this->imported          = true;
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $suppliers = Supplier::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']);
        $uoms      = UnitOfMeasure::orderBy('name')->get();

        $ingredients = Ingredient::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($i) => ['id' => (int) $i->id, 'name' => $i->name])
            ->all();

        return view('livewire.ingredients.review-document', compact(
            'suppliers', 'uoms', 'ingredients'
        ))->layout('layouts.app', ['title' => 'Review Document']);
    }
}
