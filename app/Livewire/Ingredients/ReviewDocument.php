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

        // Current per-supplier last_cost lookup keyed by ingredient_id → row
        // so we can surface the existing price + compute the delta vs. the
        // new price in the document.
        $existingLinks = collect();
        if ($this->supplierId) {
            $existingLinks = DB::table('supplier_ingredients')
                ->where('supplier_id', $this->supplierId)
                ->select('ingredient_id', 'last_cost', 'uom_id', 'pack_size')
                ->get()
                ->keyBy('ingredient_id');
        }

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

            // Old price + delta. Priority:
            //   1) This supplier's prior last_cost (same supplier → most
            //      accurate price history).
            //   2) Ingredient's purchase_price (fallback — this is what recipe
            //      costing uses, so it's the relevant benchmark when the
            //      supplier is brand new for this ingredient).
            $oldPrice       = null;
            $oldPriceSource = null; // 'supplier' | 'ingredient'
            $priceChange    = null;
            $priceChangePct = null;

            if ($match) {
                if ($alreadyLinked) {
                    $row = $existingLinks[$match['id']];
                    if ($row->last_cost !== null) {
                        $oldPrice       = (float) $row->last_cost;
                        $oldPriceSource = 'supplier';
                    }
                }
                if ($oldPrice === null && ($match['purchase_price'] ?? 0) > 0) {
                    $oldPrice       = (float) $match['purchase_price'];
                    $oldPriceSource = 'ingredient';
                }
            }

            // Reference unit for the stored price: the supplier link's unit
            // when linked, else the ingredient's base unit.
            $refUomId = $alreadyLinked
                ? (($existingLinks[$match['id']]->uom_id ?? null) ?: ($match['base_uom_id'] ?? null))
                : ($match['base_uom_id'] ?? null);

            $uomMismatch = false;
            if ($oldPrice !== null && $price > 0 && $oldPrice > 0) {
                [$priceChange, $priceChangePct, $uomMismatch] = $this->priceDiff($oldPrice, $price, $uomId, $refUomId);
            } elseif ($match && $uomId && $refUomId && $uomId !== $refUomId) {
                // No stored price to compare, but the units still disagree —
                // warn so the wrong unit isn't silently recorded.
                $map = $this->uomFactorMap();
                $a = $map[$uomId] ?? null; $b = $map[$refUomId] ?? null;
                $uomMismatch = ! ($a && $b && $a[0] === $b[0]);
            }

            // Smart default action:
            // - new match  → link (add new supplier link)
            // - no match   → create
            // - already linked + price changed → link (will update last_cost)
            // - already linked + same price    → skip
            // - unit mismatch → skip until the user fixes the unit/price
            $defaultAction = 'create';
            if ($match) {
                if ($uomMismatch) {
                    $defaultAction = 'skip';
                } elseif ($alreadyLinked) {
                    $defaultAction = $priceChange !== null ? 'link' : 'skip';
                } else {
                    $defaultAction = 'link';
                }
            }

            $this->items[] = [
                'name'             => $name,
                'code'             => $code,
                'uom_raw'          => $uomRaw,
                'uom_id'           => $uomId,
                'recipe_uom_raw'   => $recipeUomRaw,
                'recipe_uom_id'    => $recipeUomId,
                'pack_size'        => $pack,
                'price'            => $price,
                'old_price'        => $oldPrice,
                'old_price_source' => $oldPriceSource,
                'price_change'     => $priceChange,
                'price_change_pct' => $priceChangePct,
                'category'         => trim((string) ($item['category'] ?? '')) ?: null,
                'ingredient_id'    => $match['id'] ?? null,
                'matched_name'     => $match['name'] ?? null,
                'confidence'       => $match['confidence'] ?? 0,
                'already_linked'   => $alreadyLinked,
                'status'           => $status,
                'action'           => $defaultAction,
                'ref_uom_id'       => $refUomId ?? null,
                'uom_mismatch'     => $uomMismatch ?? false,
            ];
        }

        $this->recalcCounts();
    }

    private function matchIngredient(string $name, $ingredientsByName): ?array
    {
        $key = strtolower(trim($name));
        if (isset($ingredientsByName[$key])) {
            $ing = $ingredientsByName[$key];
            return [
                'id'             => $ing->id,
                'name'           => $ing->name,
                'confidence'     => 100,
                'purchase_price' => (float) ($ing->purchase_price ?? 0),
                'base_uom_id'    => $ing->base_uom_id,
            ];
        }
        $bestScore = 0; $bestMatch = null;
        foreach ($ingredientsByName as $k => $ing) {
            similar_text($key, $k, $pct);
            if ($pct > $bestScore && $pct >= 60) { $bestScore = $pct; $bestMatch = $ing; }
        }
        return $bestMatch ? [
            'id'             => $bestMatch->id,
            'name'           => $bestMatch->name,
            'confidence'     => (int) $bestScore,
            'purchase_price' => (float) ($bestMatch->purchase_price ?? 0),
            'base_uom_id'    => $bestMatch->base_uom_id,
        ] : null;
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

        $link = $this->supplierId
            ? DB::table('supplier_ingredients')
                ->where('supplier_id', $this->supplierId)
                ->where('ingredient_id', $ingredientId)
                ->first()
            : null;

        $newPrice = (float) ($this->items[$idx]['price'] ?? 0);

        // Prefer supplier-specific last_cost; fall back to the ingredient's
        // own purchase_price so a brand-new supplier link still shows a diff
        // against the benchmark recipe costing already uses.
        $old = null;
        $oldSource = null;
        if ($link && $link->last_cost !== null) {
            $old = (float) $link->last_cost;
            $oldSource = 'supplier';
        } elseif (((float) ($ing->purchase_price ?? 0)) > 0) {
            $old = (float) $ing->purchase_price;
            $oldSource = 'ingredient';
        }

        $refUomId = ($link && ($link->uom_id ?? null)) ? $link->uom_id : $ing->base_uom_id;
        [$change, $changePct, $mismatch] = $this->priceDiff($old, $newPrice, $this->items[$idx]['uom_id'] ?? null, $refUomId);

        $this->items[$idx]['ingredient_id']    = $ing->id;
        $this->items[$idx]['matched_name']     = $ing->name;
        $this->items[$idx]['confidence']       = 100;
        $this->items[$idx]['already_linked']   = (bool) $link;
        $this->items[$idx]['status']           = $link ? 'already_linked' : 'match';
        $this->items[$idx]['old_price']        = $old;
        $this->items[$idx]['old_price_source'] = $oldSource;
        $this->items[$idx]['price_change']     = $change;
        $this->items[$idx]['price_change_pct'] = $changePct;
        $this->items[$idx]['ref_uom_id']       = $refUomId;
        $this->items[$idx]['uom_mismatch']     = $mismatch;
        $this->items[$idx]['action']           = $mismatch
            ? 'skip'
            : ($link ? ($change !== null ? 'link' : 'skip') : 'link');
        $this->recalcCounts();
    }

    private function diff(?float $old, ?float $new): array
    {
        if ($old === null || $old <= 0 || $new === null || $new <= 0) return [null, null];
        if (abs($new - $old) <= 0.0001) return [null, null];
        $delta = $new - $old;
        $pct   = round(($delta / $old) * 100, 1);
        return [$delta, $pct];
    }

    /** SI families for cross-unit comparison: abbr → [family, size in family base]. */
    private const UOM_FACTORS = [
        'mg' => ['mass', 0.001], 'g' => ['mass', 1], 'kg' => ['mass', 1000],
        'ml' => ['vol', 1], 'cl' => ['vol', 10], 'dl' => ['vol', 100], 'l' => ['vol', 1000],
    ];

    /** uom_id → [family, factor] for the SI units above. */
    private function uomFactorMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (UnitOfMeasure::all() as $u) {
                $key = strtolower(trim((string) $u->abbreviation));
                if (isset(self::UOM_FACTORS[$key])) $map[$u->id] = self::UOM_FACTORS[$key];
            }
        }
        return $map;
    }

    /**
     * Price diff that respects units — the source of "apple RM0.85/pcs shown
     * as RM6.80": the invoice line's unit (e.g. kg) was compared against a
     * price stored per a DIFFERENT unit (e.g. pcs) as if they were the same.
     *
     * Returns [change, changePct, uomMismatch]:
     * - same (or unresolved) units → plain diff
     * - SI-convertible units (kg↔g, l↔ml) → invoice price converted first
     * - incomparable units (pcs vs kg) → NO diff, mismatch flagged so the UI
     *   warns instead of reporting a bogus percentage.
     */
    private function priceDiff(?float $old, ?float $new, ?int $scanUomId, ?int $refUomId): array
    {
        if ($scanUomId && $refUomId && $scanUomId !== $refUomId) {
            $map = $this->uomFactorMap();
            $a = $map[$scanUomId] ?? null;
            $b = $map[$refUomId] ?? null;
            if ($a && $b && $a[0] === $b[0]) {
                // e.g. RM6.80/kg vs price per g → compare 6.80 × (1/1000)
                $converted = $new !== null ? $new * ($b[1] / $a[1]) : null;
                [$change, $pct] = $this->diff($old, $converted);
                return [$change, $pct, false];
            }
            return [null, null, true];
        }
        [$change, $pct] = $this->diff($old, $new);
        return [$change, $pct, false];
    }

    public function fixUom(int $idx, int $uomId): void
    {
        if (! isset($this->items[$idx])) return;
        $this->items[$idx]['uom_id'] = $uomId;

        // Correcting the unit re-evaluates the price comparison and clears
        // (or raises) the mismatch warning.
        $item = $this->items[$idx];
        if ($item['ingredient_id']) {
            [$change, $changePct, $mismatch] = $this->priceDiff(
                $item['old_price'] !== null ? (float) $item['old_price'] : null,
                (float) ($item['price'] ?? 0),
                $uomId,
                $item['ref_uom_id'] ?? null,
            );
            $this->items[$idx]['price_change']     = $change;
            $this->items[$idx]['price_change_pct'] = $changePct;
            $this->items[$idx]['uom_mismatch']     = $mismatch;
            if (! $mismatch && $this->items[$idx]['action'] === 'skip' && $item['already_linked'] && $change !== null) {
                $this->items[$idx]['action'] = 'link';
            }
        }
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
            ->select('ingredient_id', 'last_cost', 'uom_id')
            ->get()
            ->keyBy('ingredient_id');

        // Cache ingredient purchase_price + base uom for fallback diff when
        // this supplier doesn't have a last_cost yet.
        $ingredientIds  = collect($this->items)->pluck('ingredient_id')->filter()->unique()->all();
        $ingredientRows = Ingredient::whereIn('id', $ingredientIds)->get(['id', 'purchase_price', 'base_uom_id'])->keyBy('id');
        $purchasePrices = $ingredientRows->map(fn ($r) => $r->purchase_price);

        foreach ($this->items as $idx => $item) {
            if (! $item['ingredient_id']) continue;
            $link   = $existing[$item['ingredient_id']] ?? null;
            $linked = (bool) $link;

            $old = null;
            $oldSource = null;
            if ($link && $link->last_cost !== null) {
                $old = (float) $link->last_cost;
                $oldSource = 'supplier';
            } elseif (((float) ($purchasePrices[$item['ingredient_id']] ?? 0)) > 0) {
                $old = (float) $purchasePrices[$item['ingredient_id']];
                $oldSource = 'ingredient';
            }

            $refUomId = ($link && ($link->uom_id ?? null))
                ? $link->uom_id
                : ($ingredientRows[$item['ingredient_id']]->base_uom_id ?? null);
            [$change, $changePct, $mismatch] = $this->priceDiff($old, (float) ($item['price'] ?? 0), $item['uom_id'] ?? null, $refUomId);

            $this->items[$idx]['already_linked']   = $linked;
            $this->items[$idx]['status']           = $linked ? 'already_linked' : 'match';
            $this->items[$idx]['old_price']        = $old;
            $this->items[$idx]['old_price_source'] = $oldSource;
            $this->items[$idx]['price_change']     = $change;
            $this->items[$idx]['price_change_pct'] = $changePct;
            $this->items[$idx]['ref_uom_id']       = $refUomId;
            $this->items[$idx]['uom_mismatch']     = $mismatch;
            $this->items[$idx]['action']           = $mismatch
                ? 'skip'
                : ($linked ? ($change !== null ? 'link' : 'skip') : 'link');
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

                    // Back-fill a baseline history row when this is the first
                    // time we're logging iph for this (ingredient, supplier).
                    // Otherwise the Price History report has nothing to diff
                    // the new row against and the price increase is invisible.
                    if ($changed && $old > 0) {
                        $priorExists = IngredientPriceHistory::where('ingredient_id', $item['ingredient_id'])
                            ->where('supplier_id', $this->supplierId)
                            ->exists();
                        if (! $priorExists) {
                            $backfillDate = $existing->updated_at
                                ? \Carbon\Carbon::parse($existing->updated_at)->toDateString()
                                : \Carbon\Carbon::parse($effectiveDate)->subDay()->toDateString();
                            if ($backfillDate >= $effectiveDate) {
                                $backfillDate = \Carbon\Carbon::parse($effectiveDate)->subDay()->toDateString();
                            }
                            IngredientPriceHistory::create([
                                'ingredient_id'  => $item['ingredient_id'],
                                'supplier_id'    => $this->supplierId,
                                'cost'           => $old,
                                'uom_id'         => $existing->uom_id ?: $uomId,
                                'effective_date' => $backfillDate,
                                'source'         => 'price_watcher_backfill',
                            ]);
                        }
                    }

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
        ))->layout('layouts.app', ['title' => 'Review Invoice']);
    }
}
