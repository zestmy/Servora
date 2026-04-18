<?php

namespace App\Livewire\Ingredients;

use App\Models\AppSetting;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientPriceHistory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class SupplierMatch extends Component
{
    use WithFileUploads;

    public $file = null;

    public string $step = 'upload'; // upload | preview | done

    // Supplier selection — now optional at upload. AI tries to detect the
    // supplier from the document; the user confirms / overrides at preview.
    public ?int $supplierId = null;
    public string $supplierName = '';

    // AI-detected supplier identity + document date (populated at preview step)
    public string $detectedSupplierName = '';
    public string $supplierMode         = 'existing'; // existing | new
    public string $newSupplierName      = '';
    public string $effectiveDate        = ''; // YYYY-MM-DD — used as price history effective_date

    // Preview items
    public array $items        = [];
    public int   $totalItems   = 0;
    public int   $matchedCount = 0;
    public int   $newCount     = 0;

    // Results
    public int $linkedCount    = 0;
    public int $createdCount   = 0;
    public int $skippedCount   = 0;
    public int $priceChangedCount = 0;

    protected function rules(): array
    {
        // Upload step only requires a file. Supplier + date are
        // AI-detected / confirmed at the preview step.
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx,pdf,jpg,jpeg,png,webp|max:10240',
        ];
    }

    protected function messages(): array
    {
        return [
            'file.mimes' => 'Supported: CSV, Excel, PDF, or image (JPG/PNG).',
            'file.max'   => 'File size must not exceed 10 MB.',
        ];
    }

    // ── Step 1: Upload & extract ──────────────────────────────────────────

    public function processUpload(): void
    {
        $this->validate();

        // Pre-seed supplier label if the user picked one up front; otherwise
        // it'll be populated by AI extraction (or stays blank for spreadsheet
        // imports, which force the user to pick at preview).
        if ($this->supplierId) {
            $supplier = Supplier::find($this->supplierId);
            $this->supplierName = $supplier?->name ?? '';
        }

        $this->effectiveDate = now()->toDateString();

        $path = $this->file->getRealPath();
        $ext  = strtolower($this->file->getClientOriginalExtension());

        if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp'])) {
            $this->extractWithAi($path);
        } else {
            $this->parseSpreadsheet($path, $ext);
        }
    }

    private function extractWithAi(string $path): void
    {
        $apiKey = AppSetting::get('openrouter_api_key');
        if (! $apiKey) {
            $this->addError('file', 'AI extraction requires an OpenRouter API key. Go to Settings > API Keys to configure it.');
            return;
        }

        $model    = 'google/gemini-2.5-flash';
        $mimeType = mime_content_type($path) ?: 'application/pdf';
        $base64   = base64_encode(file_get_contents($path));
        $dataUri  = "data:{$mimeType};base64,{$base64}";

        $prompt = <<<'PROMPT'
Extract the supplier metadata AND all product/ingredient items from this supplier document (invoice, quotation, delivery order, or price list).

Return a JSON object:
{
  "supplier_name": "exactly as shown on the letterhead, or null",
  "document_date": "YYYY-MM-DD (invoice / quotation / DO date), or null",
  "items": [
    {
      "name": "clean product name",
      "code": "supplier SKU/code or null",
      "category": "product category or null",
      "uom": "purchasing unit (kg, pcs, box, bottle, etc.)",
      "pack_size": 0,
      "recipe_uom": "smaller recipe unit (g, ml, pcs, etc.)",
      "price": 0.00,
      "quantity": 0
    }
  ]
}

## Rules:
- "supplier_name": the company issuing the document (usually the letterhead / top of page). Preserve capitalisation. If genuinely not shown, use null.
- "document_date": the date printed on the document (invoice date / quotation date / DO date). Convert to YYYY-MM-DD. If no date is shown, use null.
- Extract EVERY line item from the document.
- "name": clean product name — remove weight/volume info (1KG, 500ML) but keep size gradings for seafood (6-8, 16/20).
- "code": supplier's SKU, product code, or item number if shown.
- "uom": the unit the item is SOLD in (box, ctn, kg, pail, bag, bottle, pack, tray, etc.).
- "recipe_uom": the smaller unit used in recipes (g, ml, pcs, etc.).
- "pack_size": how many recipe_uom in 1 purchasing uom. Set to 0 if unknown.
  Examples: "SUGAR 1KG" → uom: "bag", recipe_uom: "g", pack_size: 1000
           "EGG 30'S" → uom: "tray", recipe_uom: "pcs", pack_size: 30
           "COOKING OIL 5L" → uom: "pail", recipe_uom: "ml", pack_size: 5000
- "price": the unit price shown (per purchasing uom). Extract actual prices, never default to 0 if visible.
- "quantity": ordered/delivered quantity if shown, otherwise 0.
- Use numeric values for price, pack_size, quantity.

IMPORTANT: Return ONLY valid JSON. No markdown, no commentary.
PROMPT;

        $previousTimeout = ini_get('max_execution_time');
        set_time_limit(120);

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => config('app.url', 'http://localhost'),
                    'X-Title'       => config('app.name', 'Servora'),
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model'      => $model,
                    'max_tokens' => 16384,
                    'messages'   => [
                        ['role' => 'user', 'content' => [
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                            ['type' => 'text', 'text' => $prompt],
                        ]],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            set_time_limit((int) $previousTimeout ?: 60);

            if (! $response->successful()) {
                $body = $response->json();
                $msg  = $body['error']['message'] ?? $body['message'] ?? ('HTTP ' . $response->status());
                throw new \RuntimeException($msg);
            }

            $data    = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $result  = $this->robustJsonDecode($content);

            $extracted = null;
            $detectedSupplier = null;
            $detectedDate     = null;
            if (is_array($result)) {
                $extracted        = $result['items'] ?? $result['ingredients'] ?? $result['products'] ?? $result['data'] ?? null;
                $detectedSupplier = $result['supplier_name'] ?? $result['supplier'] ?? null;
                $detectedDate     = $result['document_date'] ?? $result['date'] ?? $result['invoice_date'] ?? null;

                if (! $extracted && isset($result[0]) && is_array($result[0])) {
                    $extracted = $result;
                }
                if (! $extracted) {
                    foreach ($result as $val) {
                        if (is_array($val) && ! empty($val) && isset($val[0]) && is_array($val[0])) {
                            $extracted = $val;
                            break;
                        }
                    }
                }
            }

            if (empty($extracted)) {
                throw new \RuntimeException('AI could not extract any items from this document.');
            }

            $this->applyDetectedSupplier($detectedSupplier);
            $this->applyDetectedDate($detectedDate);
            $this->buildPreview($extracted);

        } catch (\Throwable $e) {
            set_time_limit((int) $previousTimeout ?: 60);
            Log::error('Supplier match AI extraction failed: ' . $e->getMessage());
            $this->addError('file', 'Failed to extract items: ' . $e->getMessage());
        }
    }

    private function parseSpreadsheet(string $path, string $ext): void
    {
        try {
            if ($ext === 'xlsx') {
                $reader = new \OpenSpout\Reader\XLSX\Reader();
            } else {
                $reader = new \OpenSpout\Reader\CSV\Reader();
            }
            $reader->open($path);

            $rows    = [];
            $headers = null;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(fn ($c) => trim((string) $c->getValue()), $row->getCells());

                    if ($headers === null) {
                        $headers = array_map('strtolower', array_map('trim', $cells));
                        continue;
                    }
                    if (array_filter($cells) === []) continue;

                    $rows[] = array_combine($headers, array_pad($cells, count($headers), ''));
                }
                break;
            }
            $reader->close();

            if (empty($rows)) {
                $this->addError('file', 'The file has no data rows.');
                return;
            }

            // Convert to the same format as AI output
            $extracted = [];
            foreach ($rows as $row) {
                $name = $row['name'] ?? $row['item'] ?? $row['product'] ?? $row['ingredient'] ?? $row['description'] ?? '';
                if (! trim($name)) continue;

                $extracted[] = [
                    'name'       => trim($name),
                    'code'       => trim($row['code'] ?? $row['sku'] ?? $row['item_code'] ?? ''),
                    'uom'        => trim($row['uom'] ?? $row['unit'] ?? ''),
                    'recipe_uom' => trim($row['recipe_uom'] ?? ''),
                    'pack_size'  => floatval($row['pack_size'] ?? 0),
                    'price'      => floatval(preg_replace('/[^\d.\-]/', '', str_replace(',', '', $row['price'] ?? $row['unit_price'] ?? '0'))),
                    'category'   => trim($row['category'] ?? ''),
                ];
            }

            if (empty($extracted)) {
                $this->addError('file', 'No recognizable items found in the file.');
                return;
            }

            $this->buildPreview($extracted);

        } catch (\Throwable $e) {
            $this->addError('file', 'Could not parse file: ' . $e->getMessage());
        }
    }

    // ── Build preview with ingredient matching ────────────────────────────

    private function buildPreview(array $extracted): void
    {
        $companyId = Auth::user()->company_id;

        $ingredientsByName = Ingredient::where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['baseUom'])
            ->get()
            ->keyBy(fn ($i) => strtolower(trim($i->name)));

        // Check which ingredients already have this supplier linked
        $existingLinks = DB::table('supplier_ingredients')
            ->where('supplier_id', $this->supplierId)
            ->pluck('ingredient_id')
            ->flip();

        $uomsByAbbr = UnitOfMeasure::all()->mapWithKeys(fn ($u) => [strtolower($u->abbreviation) => $u]);
        $uomsByName = UnitOfMeasure::all()->mapWithKeys(fn ($u) => [strtolower($u->name) => $u]);

        $this->items = [];

        foreach ($extracted as $item) {
            $name = trim($item['name'] ?? '');
            if (! $name) continue;

            $match = $this->matchIngredient($name, $ingredientsByName);

            $uomRaw  = trim($item['uom'] ?? '');
            $uomId   = $this->resolveUom($uomRaw, $uomsByAbbr, $uomsByName);
            $price   = floatval($item['price'] ?? 0);
            $code    = trim($item['code'] ?? '') ?: null;
            $packSize = floatval($item['pack_size'] ?? 0);
            $recipeUomRaw = trim($item['recipe_uom'] ?? '');
            $recipeUomId  = $recipeUomRaw ? $this->resolveUom($recipeUomRaw, $uomsByAbbr, $uomsByName) : null;

            $alreadyLinked = false;
            $status = 'new'; // new | match | already_linked
            if ($match) {
                if (isset($existingLinks[$match['id']])) {
                    $alreadyLinked = true;
                    $status = 'already_linked';
                } else {
                    $status = 'match';
                }
            }

            $this->items[] = [
                'name'            => $name,
                'code'            => $code,
                'uom_raw'         => $uomRaw,
                'uom_id'          => $uomId,
                'recipe_uom_raw'  => $recipeUomRaw,
                'recipe_uom_id'   => $recipeUomId,
                'pack_size'       => $packSize,
                'price'           => $price,
                'category'        => trim($item['category'] ?? '') ?: null,
                'ingredient_id'   => $match['id'] ?? null,
                'matched_name'    => $match['name'] ?? null,
                'confidence'      => $match['confidence'] ?? 0,
                'already_linked'  => $alreadyLinked,
                'status'          => $status,
                'action'          => $alreadyLinked ? 'skip' : ($match ? 'link' : 'create'),
                // action: link (add supplier to existing), create (new ingredient), skip
            ];
        }

        $this->totalItems   = count($this->items);
        $this->matchedCount = collect($this->items)->whereIn('status', ['match', 'already_linked'])->count();
        $this->newCount     = collect($this->items)->where('status', 'new')->count();
        $this->step         = 'preview';
    }

    private function matchIngredient(string $name, $ingredientsByName): ?array
    {
        $key = strtolower(trim($name));

        if (isset($ingredientsByName[$key])) {
            $ing = $ingredientsByName[$key];
            return ['id' => $ing->id, 'name' => $ing->name, 'confidence' => 100];
        }

        $bestMatch = null;
        $bestScore = 0;
        foreach ($ingredientsByName as $ingKey => $ing) {
            similar_text($key, $ingKey, $pct);
            if ($pct > $bestScore && $pct >= 60) {
                $bestScore = $pct;
                $bestMatch = $ing;
            }
        }

        if ($bestMatch) {
            return ['id' => $bestMatch->id, 'name' => $bestMatch->name, 'confidence' => (int) $bestScore];
        }

        return null;
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

    // ── Detected supplier + date handling ────────────────────────────────

    /**
     * Try to match an AI-detected supplier name against existing suppliers;
     * otherwise leave the user on the "new supplier" path with the name
     * pre-filled so they can create it with one click at the preview step.
     */
    private function applyDetectedSupplier(?string $name): void
    {
        $this->detectedSupplierName = trim((string) $name);

        if ($this->supplierId) {
            // User pre-selected a supplier — respect their choice.
            return;
        }

        if ($this->detectedSupplierName === '') {
            $this->supplierMode = 'existing';
            return;
        }

        $companyId = Auth::user()->company_id;
        $match = Supplier::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($this->detectedSupplierName)])
            ->first();

        if ($match) {
            $this->supplierId   = $match->id;
            $this->supplierName = $match->name;
            $this->supplierMode = 'existing';
        } else {
            $this->supplierMode    = 'new';
            $this->newSupplierName = $this->detectedSupplierName;
        }
    }

    private function applyDetectedDate(?string $isoDate): void
    {
        $candidate = trim((string) $isoDate);
        if ($candidate === '') return;
        try {
            $this->effectiveDate = \Carbon\Carbon::parse($candidate)->toDateString();
        } catch (\Throwable $e) {
            // Leave the default (today).
        }
    }

    /** Called from the preview step when the user clicks "Create this supplier". */
    public function createSupplier(): void
    {
        $this->validate([
            'newSupplierName' => 'required|string|max:255',
        ], [
            'newSupplierName.required' => 'Give the new supplier a name.',
        ]);

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
        session()->flash('success', "Supplier \"{$supplier->name}\" created and linked.");

        // Recompute which items are already linked to this supplier.
        $this->refreshExistingLinks();
    }

    private function refreshExistingLinks(): void
    {
        if (! $this->supplierId) return;
        $existingLinks = DB::table('supplier_ingredients')
            ->where('supplier_id', $this->supplierId)
            ->pluck('ingredient_id')
            ->flip();

        foreach ($this->items as $idx => $item) {
            if (! $item['ingredient_id']) continue;
            $linked = isset($existingLinks[$item['ingredient_id']]);
            $this->items[$idx]['already_linked'] = $linked;
            $this->items[$idx]['status']         = $linked ? 'already_linked' : 'match';
            $this->items[$idx]['action']         = $linked ? 'skip' : 'link';
        }
        $this->recalcCounts();
    }

    // ── Fix actions ───────────────────────────────────────────────────────

    public function fixMatch(int $idx, int $ingredientId): void
    {
        if (! isset($this->items[$idx])) return;

        $ingredient = Ingredient::find($ingredientId);
        if (! $ingredient) return;

        $alreadyLinked = DB::table('supplier_ingredients')
            ->where('supplier_id', $this->supplierId)
            ->where('ingredient_id', $ingredientId)
            ->exists();

        $this->items[$idx]['ingredient_id']  = $ingredient->id;
        $this->items[$idx]['matched_name']   = $ingredient->name;
        $this->items[$idx]['confidence']      = 100;
        $this->items[$idx]['already_linked']  = $alreadyLinked;
        $this->items[$idx]['status']          = $alreadyLinked ? 'already_linked' : 'match';
        $this->items[$idx]['action']          = $alreadyLinked ? 'skip' : 'link';

        $this->recalcCounts();
    }

    public function fixUom(int $idx, int $uomId): void
    {
        if (! isset($this->items[$idx])) return;
        $this->items[$idx]['uom_id'] = $uomId;
    }

    public function setAction(int $idx, string $action): void
    {
        if (! isset($this->items[$idx])) return;
        if (! in_array($action, ['link', 'create', 'skip'])) return;
        $this->items[$idx]['action'] = $action;
    }

    private function recalcCounts(): void
    {
        $this->matchedCount = collect($this->items)->whereIn('status', ['match', 'already_linked'])->count();
        $this->newCount     = collect($this->items)->where('status', 'new')->count();
    }

    // ── Step 2: Import ────────────────────────────────────────────────────

    public function import(): void
    {
        $user      = Auth::user();
        $companyId = $user->company_id;

        // Auto-create the supplier on the fly if the user left the form in
        // "new supplier" mode without pressing the Create button.
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

        // Use the document's effective date for every price history row so
        // the timeline reflects when the prices actually took effect.
        $effectiveDate = $this->effectiveDate ?: now()->toDateString();

        $linked       = 0;
        $created      = 0;
        $skipped      = 0;
        $priceChanged = 0;

        foreach ($this->items as $item) {
            if ($item['action'] === 'skip') {
                $skipped++;
                continue;
            }

            if ($item['action'] === 'link' && $item['ingredient_id']) {
                $uomId = $item['uom_id'] ?? UnitOfMeasure::first()?->id;
                $pack  = max($item['pack_size'], 0.0001) ?: 1;
                $price = floatval($item['price'] ?? 0);

                $existing = DB::table('supplier_ingredients')
                    ->where('supplier_id', $this->supplierId)
                    ->where('ingredient_id', $item['ingredient_id'])
                    ->first();

                if ($existing) {
                    // Already linked — detect price change and refresh last_cost.
                    $oldPrice = $existing->last_cost !== null ? (float) $existing->last_cost : null;
                    $changed  = $price > 0 && $oldPrice !== null && abs($price - $oldPrice) > 0.0001;

                    DB::table('supplier_ingredients')
                        ->where('id', $existing->id)
                        ->update([
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
                    $packSize    = max($item['pack_size'], 0.0001) ?: 1;
                    $price       = floatval($item['price'] ?? 0);
                    $currentCost = $packSize > 0 && $price > 0 ? round($price / $packSize, 4) : 0;

                    $ingredient = Ingredient::create([
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

                    $ingredient->suppliers()->attach($this->supplierId, [
                        'supplier_sku' => $item['code'],
                        'last_cost'    => $price > 0 ? $price : null,
                        'uom_id'       => $baseUomId,
                        'pack_size'    => $packSize,
                        'is_preferred' => true,
                    ]);

                    if ($price > 0) {
                        IngredientPriceHistory::create([
                            'ingredient_id'  => $ingredient->id,
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

        $this->linkedCount       = $linked;
        $this->createdCount      = $created;
        $this->skippedCount      = $skipped;
        $this->priceChangedCount = $priceChanged;
        $this->step              = 'done';
    }

    public function restart(): void
    {
        $this->file                = null;
        $this->step                = 'upload';
        $this->items               = [];
        $this->totalItems          = 0;
        $this->matchedCount        = 0;
        $this->newCount            = 0;
        $this->linkedCount         = 0;
        $this->createdCount        = 0;
        $this->skippedCount        = 0;
        $this->priceChangedCount   = 0;
        $this->detectedSupplierName = '';
        $this->newSupplierName      = '';
        $this->supplierMode         = 'existing';
        $this->effectiveDate        = '';
        $this->resetValidation();
    }

    public function render()
    {
        $companyId = Auth::user()->company_id;

        $suppliers = Supplier::where('company_id', $companyId)
            ->orderBy('name')
            ->select('id', 'name')
            ->get();

        $uoms = UnitOfMeasure::orderBy('name')->get();

        $ingredients = [];
        if ($this->step === 'preview') {
            $ingredients = Ingredient::where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->select('id', 'name')
                ->get();
        }

        return view('livewire.ingredients.supplier-match', compact('suppliers', 'uoms', 'ingredients'))
            ->layout('layouts.app', ['title' => 'Supplier Product Match']);
    }

    private function robustJsonDecode(string $raw): ?array
    {
        $raw = trim($raw);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) return $decoded;

        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $raw, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) return $decoded;
        }

        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (is_array($decoded)) return $decoded;
        }

        return null;
    }
}
