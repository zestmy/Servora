<?php

namespace App\Livewire\Ingredients;

use App\Models\AppSetting;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\SupplierIngredient;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class Import extends Component
{
    use WithFileUploads;

    public $file = null;

    public string $step = 'upload'; // upload | mapping | preview | done

    // Raw parsed data
    public array $fileHeaders  = [];
    public array $fileDataRows = [];

    // AI column mapping: system_field => file_header
    public array $columnMapping = [];
    public bool  $aiMapped      = false;
    public bool  $aiMapping      = false;
    public string $aiError       = '';

    // System fields definition
    public const SYSTEM_FIELDS = [
        'name'           => ['label' => 'Name',           'required' => true,  'description' => 'Ingredient name'],
        'code'           => ['label' => 'Code',           'required' => false, 'description' => 'Internal code / SKU'],
        'category'       => ['label' => 'Category',       'required' => false, 'description' => 'Main cost category'],
        'base_uom'       => ['label' => 'Base UOM',       'required' => true,  'description' => 'Purchasing unit of measure'],
        'recipe_uom'     => ['label' => 'Recipe UOM',     'required' => false, 'description' => 'Recipe unit of measure'],
        'purchase_price' => ['label' => 'Purchase Price',  'required' => false, 'description' => 'Price per pack'],
        'pack_size'      => ['label' => 'Pack Size',       'required' => false, 'description' => 'Pack size in base UOM'],
        'yield_percent'  => ['label' => 'Yield %',         'required' => false, 'description' => 'Yield percentage (0-100)'],
        'is_active'      => ['label' => 'Active',          'required' => false, 'description' => 'Active status (yes/no)'],
        'supplier'       => ['label' => 'Default Supplier','required' => false, 'description' => 'Preferred supplier name'],
        'type'           => ['label' => 'Type',            'required' => false, 'description' => 'ingredient or prep (default: auto-detect)'],
    ];

    public array $rows        = [];
    public int   $totalRows   = 0;
    public int   $validRows   = 0;
    public int   $importedCount    = 0;
    public int   $skippedCount     = 0;
    public int   $prepCreatedCount = 0;

    protected function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx,pdf|max:10240',
        ];
    }

    protected function messages(): array
    {
        return [
            'file.mimes' => 'Only CSV (.csv), Excel (.xlsx), and PDF files are supported.',
            'file.max'   => 'File size must not exceed 10 MB.',
        ];
    }

    // ── Step 1: Upload & parse headers ──────────────────────────────────────

    public function processUpload(): void
    {
        $this->validate();

        $path = $this->file->getRealPath();
        $ext  = strtolower($this->file->getClientOriginalExtension());

        // PDF files use AI vision extraction — different flow
        if ($ext === 'pdf') {
            $this->processPdfUpload($path);
            return;
        }

        try {
            $parsed = ($ext === 'xlsx') ? $this->parseXlsx($path) : $this->parseCsv($path);
        } catch (\Throwable $e) {
            $this->addError('file', 'Could not parse file: ' . $e->getMessage());
            return;
        }

        if (empty($parsed['headers'])) {
            $this->addError('file', 'The file appears to be empty or has no header row.');
            return;
        }

        if (empty($parsed['rows'])) {
            $this->addError('file', 'The file has headers but no data rows.');
            return;
        }

        $this->fileHeaders  = $parsed['headers'];
        $this->fileDataRows = $parsed['rows'];

        // Try exact matching first
        $exactMapping = $this->tryExactMapping($this->fileHeaders);

        // Check if exact mapping found both required fields
        $hasName    = ! empty($exactMapping['name']);
        $hasBaseUom = ! empty($exactMapping['base_uom']);

        if ($hasName && $hasBaseUom) {
            $this->columnMapping = $exactMapping;
            $this->aiMapped      = false;
            $this->step          = 'mapping';
        } else {
            $this->step = 'mapping';
            $this->runAiMapping();
        }
    }

    // ── PDF extraction via AI vision ───────────────────────────────────────

    private function processPdfUpload(string $path): void
    {
        $apiKey = AppSetting::get('openrouter_api_key');
        if (! $apiKey) {
            $this->addError('file', 'PDF import requires an OpenRouter API key. Go to Settings > API Keys to configure it.');
            return;
        }

        // PDF extraction requires a vision-capable model
        $model = 'google/gemini-2.5-flash';

        $mimeType = mime_content_type($path) ?: 'application/pdf';
        $base64   = base64_encode(file_get_contents($path));
        $dataUri  = "data:{$mimeType};base64,{$base64}";

        $prompt = <<<'PROMPT'
Extract all ingredient/item data from this PDF document. This could be a supplier product list, inventory sheet, price list, ingredient master list, or any document containing food & beverage items.

Return a JSON object with this structure:
{
  "items": [
    {
      "name": "item name as printed",
      "code": "item code/SKU or null",
      "category": "category if shown or null",
      "base_uom": "unit of measure (kg, g, L, ml, pcs, box, ctn, bottle, etc.) or null",
      "recipe_uom": "recipe unit if different from base_uom, or null",
      "purchase_price": 0.00,
      "pack_size": 1,
      "yield_percent": 100,
      "is_active": "yes",
      "supplier": "supplier name if shown or null",
      "type": "ingredient or prep"
    }
  ]
}

Rules:
- Extract EVERY item/ingredient row from the document
- Use numeric values for prices, pack_size, yield_percent
- For type: classify as "prep" if the item is clearly a prepared/cooked item (sauce, stock, marinade, dressing, batter, dough, blend, puree, etc.), otherwise "ingredient"
- If unit of measure is not shown, make your best guess based on the item name (e.g. liquids = L, meats = kg, small items = pcs)
- If price is not shown, use 0
- If a supplier name appears in the document header/title, include it for all items
- Capture the data exactly as printed — do not modify item names

IMPORTANT: Return ONLY valid JSON. No markdown, no explanation, no commentary — just the JSON object.
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
                Log::error('PDF extraction API error', ['status' => $response->status(), 'body' => $body]);
                $msg = $body['error']['message'] ?? $body['message'] ?? ('HTTP ' . $response->status());
                throw new \RuntimeException($msg);
            }

            $data    = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            Log::info('PDF extraction raw response', ['length' => strlen($content), 'first100' => mb_substr($content, 0, 100)]);

            $result = $this->robustJsonDecode($content);

            Log::info('PDF extraction decode result', [
                'is_array' => is_array($result),
                'keys' => is_array($result) ? array_keys($result) : null,
            ]);

            // Try to find the items array — AI might use different keys
            $items = null;
            if (is_array($result)) {
                if (! empty($result['items'])) {
                    $items = $result['items'];
                } elseif (! empty($result['ingredients'])) {
                    $items = $result['ingredients'];
                } elseif (! empty($result['data'])) {
                    $items = $result['data'];
                } elseif (isset($result[0]) && is_array($result[0])) {
                    // AI returned a flat array of items
                    $items = $result;
                } else {
                    // Check if any key contains an array of objects
                    foreach ($result as $key => $val) {
                        if (is_array($val) && ! empty($val) && isset($val[0]) && is_array($val[0])) {
                            $items = $val;
                            break;
                        }
                    }
                }
            }

            if (empty($items)) {
                Log::error('PDF extraction: no items found in response', ['result_keys' => is_array($result) ? array_keys($result) : 'not_array']);
                throw new \RuntimeException('AI could not extract any items from this PDF. Please ensure the PDF contains a readable list of ingredients or products.');
            }

            // Convert AI output to the same format as CSV/XLSX parsing
            $systemFieldKeys = array_keys(self::SYSTEM_FIELDS);
            $this->fileHeaders  = $systemFieldKeys;
            $this->fileDataRows = [];

            foreach ($items as $item) {
                $row = [];
                foreach ($systemFieldKeys as $key) {
                    $val = $item[$key] ?? '';
                    $row[$key] = is_scalar($val) ? (string) $val : '';
                }
                $this->fileDataRows[] = $row;
            }

            // PDF extraction already maps columns perfectly — go straight to mapping with all fields pre-mapped
            $this->columnMapping = [];
            foreach ($systemFieldKeys as $key) {
                $this->columnMapping[$key] = $key;
            }

            $this->aiMapped = true;
            $this->step     = 'mapping';

        } catch (\Throwable $e) {
            set_time_limit((int) $previousTimeout ?: 60);
            Log::error('PDF ingredient extraction failed: ' . $e->getMessage());
            $this->addError('file', 'Failed to extract data from PDF: ' . $e->getMessage());
        }
    }

    // ── Exact header matching ───────────────────────────────────────────────

    private function tryExactMapping(array $headers): array
    {
        $mapping = [];
        $systemKeys = array_keys(self::SYSTEM_FIELDS);

        $normalizedMap = [];
        foreach ($headers as $header) {
            $normalized = strtolower(trim(str_replace([' ', '-', '_'], '_', $header)));
            $normalizedMap[$normalized] = $header;
        }

        $aliases = [
            'name'           => ['name', 'ingredient_name', 'ingredient', 'item_name', 'item', 'product_name', 'product', 'description'],
            'code'           => ['code', 'sku', 'item_code', 'ingredient_code', 'product_code', 'internal_code'],
            'category'       => ['category', 'cost_category', 'ingredient_category', 'group'],
            'base_uom'       => ['base_uom', 'uom', 'unit', 'unit_of_measure', 'purchase_uom', 'purchasing_unit', 'base_unit'],
            'recipe_uom'     => ['recipe_uom', 'recipe_unit', 'cooking_uom', 'cooking_unit'],
            'purchase_price' => ['purchase_price', 'price', 'cost', 'unit_price', 'unit_cost', 'buy_price'],
            'pack_size'      => ['pack_size', 'pack', 'package_size', 'qty_per_pack', 'quantity_per_pack'],
            'yield_percent'  => ['yield_percent', 'yield', 'yield_%', 'yield_pct', 'yield_percentage'],
            'is_active'      => ['is_active', 'active', 'status', 'enabled'],
            'supplier'       => ['supplier', 'default_supplier', 'preferred_supplier', 'supplier_name', 'vendor', 'vendor_name'],
            'type'           => ['type', 'item_type', 'ingredient_type'],
        ];

        foreach ($systemKeys as $field) {
            foreach ($aliases[$field] ?? [] as $alias) {
                if (isset($normalizedMap[$alias])) {
                    $mapping[$field] = $normalizedMap[$alias];
                    break;
                }
            }
        }

        return $mapping;
    }

    // ── AI Column Mapping ───────────────────────────────────────────────────

    public function runAiMapping(): void
    {
        $this->aiMapping = true;
        $this->aiError   = '';

        $apiKey = AppSetting::get('openrouter_api_key');

        if (! $apiKey) {
            $this->columnMapping = $this->tryExactMapping($this->fileHeaders);
            $this->aiMapped      = false;
            $this->aiMapping     = false;
            $this->aiError       = 'OpenRouter API key not configured. Using basic column matching. Go to Settings > API Keys to enable AI mapping.';
            return;
        }

        $model = AppSetting::get('openrouter_model') ?: 'anthropic/claude-sonnet-4';

        $sampleRows = array_slice($this->fileDataRows, 0, 3);
        $sampleText = '';
        foreach ($sampleRows as $i => $row) {
            $sampleText .= "Row " . ($i + 1) . ": " . json_encode($row) . "\n";
        }

        $systemFields = [];
        foreach (self::SYSTEM_FIELDS as $key => $info) {
            $systemFields[] = "{$key} ({$info['description']}" . ($info['required'] ? ', REQUIRED' : '') . ")";
        }

        $prompt = "You are a data column mapper for a food & beverage ingredient management system.\n\n"
            . "The user uploaded a spreadsheet with these column headers:\n"
            . json_encode($this->fileHeaders) . "\n\n"
            . "Here are sample data rows:\n{$sampleText}\n"
            . "Map each file column to the best matching system field. System fields:\n"
            . implode("\n", $systemFields) . "\n\n"
            . "Return a JSON object where keys are system field names and values are the EXACT original file column header strings. "
            . "Only include mappings you are confident about. If a file column doesn't match any system field, omit it. "
            . "The 'name' and 'base_uom' fields are required — try hard to find matches for them.\n\n"
            . "Return ONLY the JSON object, no other text.";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => config('app.url', 'http://localhost'),
                    'X-Title'       => config('app.name', 'Servora'),
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model'      => $model,
                    'max_tokens' => 1024,
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('API returned status ' . $response->status());
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? '';
            $mapped = json_decode($content, true);

            if (! is_array($mapped)) {
                throw new \RuntimeException('Invalid response format from AI');
            }

            $validMapping = [];
            $headerLower = array_map('strtolower', $this->fileHeaders);

            foreach ($mapped as $sysField => $fileHeader) {
                if (! array_key_exists($sysField, self::SYSTEM_FIELDS)) continue;
                if (! is_string($fileHeader)) continue;

                $idx = array_search(strtolower($fileHeader), $headerLower);
                if ($idx !== false) {
                    $validMapping[$sysField] = $this->fileHeaders[$idx];
                }
            }

            $exactMapping = $this->tryExactMapping($this->fileHeaders);
            $this->columnMapping = array_merge($validMapping, $exactMapping);
            $this->aiMapped      = true;

        } catch (\Throwable $e) {
            Log::warning('AI column mapping failed: ' . $e->getMessage());
            $this->columnMapping = $this->tryExactMapping($this->fileHeaders);
            $this->aiMapped      = false;
            $this->aiError       = 'AI mapping failed: ' . $e->getMessage() . '. Using basic column matching.';
        }

        $this->aiMapping = false;
    }

    // ── Step 2: Confirm mapping & build preview ─────────────────────────────

    public function confirmMapping(): void
    {
        if (empty($this->columnMapping['name'])) {
            $this->addError('mapping', 'The "Name" field must be mapped to a column.');
            return;
        }
        if (empty($this->columnMapping['base_uom'])) {
            $this->addError('mapping', 'The "Base UOM" field must be mapped to a column.');
            return;
        }

        $this->buildPreview();
        $this->step = 'preview';
    }

    private function buildPreview(): void
    {
        // Load lookup tables once
        $uomsByAbbr = UnitOfMeasure::all()->mapWithKeys(fn ($u) => [strtolower($u->abbreviation) => $u->id]);
        $uomsByName = UnitOfMeasure::all()->mapWithKeys(fn ($u) => [strtolower($u->name) => $u->id]);
        $catsByName = IngredientCategory::whereNull('parent_id')
            ->get()
            ->mapWithKeys(fn ($c) => [strtolower($c->name) => $c->id]);

        $companyId       = Auth::user()->company_id;
        $suppliersByName = Supplier::where('company_id', $companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id]);

        $mapping = $this->columnMapping;

        // Check if "type" column is mapped — if so, use it; otherwise collect names for AI detection
        $hasTypeColumn = ! empty($mapping['type']);

        $this->rows = [];
        $namesForAi = []; // index => name, for AI prep detection

        foreach ($this->fileDataRows as $i => $raw) {
            $rowNum    = $i + 2;
            $rowErrors = [];

            $getValue = function (string $sysField) use ($raw, $mapping): string {
                $header = $mapping[$sysField] ?? null;
                if (! $header) return '';
                return trim($raw[$header] ?? '');
            };

            // Name
            $name = $getValue('name');
            if (! $name) {
                $rowErrors[] = 'Name is required';
            }

            // Base UOM
            $baseUomRaw = $getValue('base_uom');
            $baseUomKey = strtolower($baseUomRaw);
            $baseUomId  = $uomsByAbbr[$baseUomKey] ?? $uomsByName[$baseUomKey] ?? null;
            $baseUomNeedsfix = false;
            if (! $baseUomId && $baseUomRaw) {
                $baseUomNeedsfix = true; // user can fix via dropdown
            } elseif (! $baseUomId) {
                $rowErrors[] = 'Base UOM is required';
            }

            // Recipe UOM (defaults to base UOM)
            $recipeUomRaw = $getValue('recipe_uom');
            $recipeUomKey = strtolower($recipeUomRaw);
            $recipeUomId  = null;
            $recipeUomNeedsfix = false;
            if ($recipeUomKey) {
                $recipeUomId = $uomsByAbbr[$recipeUomKey] ?? $uomsByName[$recipeUomKey] ?? null;
                if (! $recipeUomId) {
                    $recipeUomNeedsfix = true; // user can fix via dropdown
                }
            }
            $recipeUomId = $recipeUomId ?? $baseUomId;

            // Category
            $catRaw = $getValue('category');
            $catKey = strtolower($catRaw);
            $catId  = $catKey ? ($catsByName[$catKey] ?? null) : null;
            if ($catKey && ! $catId) {
                $rowErrors[] = 'Category "' . $catRaw . '" not found';
            }

            // Numeric fields
            $ppRaw = $getValue('purchase_price');
            $purchasePrice = is_numeric($ppRaw) ? max(0, (float) $ppRaw) : 0.0;

            $psRaw = $getValue('pack_size');
            $packSize = is_numeric($psRaw) ? max(0.0001, (float) $psRaw) : 1.0;

            $ypRaw = $getValue('yield_percent');
            $yieldPercent = is_numeric($ypRaw) ? min(100, max(0.01, (float) $ypRaw)) : 100.0;

            $isActive = $this->parseBool($getValue('is_active') ?: 'yes');

            // Supplier
            $supplierRaw = $getValue('supplier');
            $supplierKey = strtolower($supplierRaw);
            $supplierId  = $supplierKey ? ($suppliersByName[$supplierKey] ?? null) : null;
            $supplierIsNew = false;
            if ($supplierKey && ! $supplierId) {
                $supplierId = $this->fuzzyMatchSupplier($supplierRaw, $suppliersByName);
                if (! $supplierId) {
                    $supplierIsNew = true;
                }
            }

            // Type (prep or ingredient)
            $isPrep = false;
            if ($hasTypeColumn) {
                $typeRaw = strtolower($getValue('type'));
                $isPrep = in_array($typeRaw, ['prep', 'prep item', 'prep_item', 'prepared', 'recipe']);
            } else {
                // Collect name for AI detection later
                if ($name) {
                    $namesForAi[$i] = $name;
                }
            }

            // Row needs fix if UOMs are unresolved (not a hard error — user can pick)
            $needsUomFix = $baseUomNeedsfix || $recipeUomNeedsfix;

            $this->rows[] = [
                'row'                    => $rowNum,
                'name'                   => $name,
                'code'                   => $getValue('code') ?: null,
                'category_label'         => $catRaw,
                'ingredient_category_id' => $catId,
                'base_uom_label'         => $baseUomRaw,
                'base_uom_id'            => $baseUomId,
                'base_uom_needsfix'      => $baseUomNeedsfix,
                'recipe_uom_label'       => $recipeUomRaw,
                'recipe_uom_id'          => $recipeUomId,
                'recipe_uom_needsfix'    => $recipeUomNeedsfix,
                'purchase_price'         => $purchasePrice,
                'pack_size'              => $packSize,
                'yield_percent'          => $yieldPercent,
                'is_active'              => $isActive,
                'supplier_label'         => $supplierRaw,
                'supplier_id'            => $supplierId,
                'supplier_is_new'        => $supplierIsNew,
                'is_prep'                => $isPrep,
                'errors'                 => $rowErrors,
                'skip'                   => ! empty($rowErrors) || $needsUomFix,
            ];
        }

        // Run AI prep detection if no type column was mapped
        if (! $hasTypeColumn && ! empty($namesForAi)) {
            $this->detectPrepItems($namesForAi);
        }

        $this->totalRows = count($this->rows);
        $this->validRows = collect($this->rows)->where('skip', false)->count();
    }

    // ── AI Prep Item Detection ──────────────────────────────────────────────

    private function detectPrepItems(array $namesForAi): void
    {
        $apiKey = AppSetting::get('openrouter_api_key');
        if (! $apiKey) return; // No AI available — everything stays as ingredient

        $model = AppSetting::get('openrouter_model') ?: 'anthropic/claude-sonnet-4';

        // Build numbered list of item names
        $namesList = '';
        foreach ($namesForAi as $idx => $name) {
            $namesList .= ($idx + 1) . ". {$name}\n";
        }

        $prompt = "You are classifying food & beverage items for a restaurant management system.\n\n"
            . "Classify each item as either \"ingredient\" (raw purchased item) or \"prep\" (prepared/cooked item made in-house from other ingredients).\n\n"
            . "PREP items are things like: sauces, stocks, broths, marinades, dressings, batters, doughs, "
            . "compound butters, spice mixes/blends, pre-cut/portioned items, blanched vegetables, "
            . "pastry creams, ganache, simple syrup, infused oils, house-made pastes, rubs, glazes, "
            . "pre-cooked proteins, soup bases, purees, coulis, croutons, breadcrumbs (house-made), "
            . "pickled items, fermented items, cured items.\n\n"
            . "INGREDIENT items are things like: raw meat, fresh vegetables, fruits, dairy products, "
            . "eggs, flour, sugar, salt, oil, vinegar, canned goods, dried goods, spices (individual), "
            . "condiments (store-bought), beverages, packaging materials, cleaning supplies.\n\n"
            . "Items to classify:\n{$namesList}\n"
            . "Return a JSON object with a single key \"prep_indices\" containing an array of 1-based item numbers that are PREP items. "
            . "Only include items you are fairly confident are prep items. When in doubt, classify as ingredient.\n"
            . "Example: {\"prep_indices\": [2, 5, 8]}";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                    'HTTP-Referer'  => config('app.url', 'http://localhost'),
                    'X-Title'       => config('app.name', 'Servora'),
                ])
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model'      => $model,
                    'max_tokens' => 1024,
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) return;

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? '';
            $result = json_decode($content, true);

            if (! is_array($result) || ! isset($result['prep_indices'])) return;

            // Map 1-based indices back to row indices
            $indexKeys = array_keys($namesForAi);
            foreach ($result['prep_indices'] as $oneBasedIdx) {
                $zeroBasedIdx = $oneBasedIdx - 1;
                if (isset($indexKeys[$zeroBasedIdx])) {
                    $rowIdx = $indexKeys[$zeroBasedIdx];
                    if (isset($this->rows[$rowIdx])) {
                        $this->rows[$rowIdx]['is_prep'] = true;
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::warning('AI prep detection failed: ' . $e->getMessage());
            // Silently fail — everything stays as ingredient
        }
    }

    // ── Toggle prep status from preview ─────────────────────────────────────

    public function togglePrep(int $index): void
    {
        if (isset($this->rows[$index])) {
            $this->rows[$index]['is_prep'] = ! $this->rows[$index]['is_prep'];
        }
    }

    public function fixBaseUom(int $index, $uomId): void
    {
        if (! isset($this->rows[$index]) || ! $uomId) return;

        $uom = UnitOfMeasure::find($uomId);
        if (! $uom) return;

        $this->rows[$index]['base_uom_id'] = $uom->id;
        $this->rows[$index]['base_uom_label'] = $uom->abbreviation;
        $this->rows[$index]['base_uom_needsfix'] = false;

        // If recipe UOM was defaulting to base, update it too
        if (empty($this->rows[$index]['recipe_uom_id'])) {
            $this->rows[$index]['recipe_uom_id'] = $uom->id;
        }

        $this->recalcRowSkip($index);
    }

    public function fixRecipeUom(int $index, $uomId): void
    {
        if (! isset($this->rows[$index]) || ! $uomId) return;

        $uom = UnitOfMeasure::find($uomId);
        if (! $uom) return;

        $this->rows[$index]['recipe_uom_id'] = $uom->id;
        $this->rows[$index]['recipe_uom_label'] = $uom->abbreviation;
        $this->rows[$index]['recipe_uom_needsfix'] = false;

        $this->recalcRowSkip($index);
    }

    private function recalcRowSkip(int $index): void
    {
        $row = $this->rows[$index];
        $hasErrors = ! empty($row['errors']);
        $needsUomFix = ! empty($row['base_uom_needsfix']) || ! empty($row['recipe_uom_needsfix']);
        $this->rows[$index]['skip'] = $hasErrors || $needsUomFix;

        // Recalculate valid count
        $this->validRows = collect($this->rows)->where('skip', false)->count();
    }

    // ── Fuzzy supplier match ────────────────────────────────────────────────

    private function fuzzyMatchSupplier(string $input, $suppliersByName): ?int
    {
        $inputLower = strtolower($input);
        $bestMatch  = null;
        $bestScore  = 0;

        foreach ($suppliersByName as $name => $id) {
            if (str_contains($inputLower, $name) || str_contains($name, $inputLower)) {
                $score = similar_text($inputLower, $name);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $id;
                }
            }

            $pct = 0;
            similar_text($inputLower, $name, $pct);
            if ($pct >= 80 && $pct > $bestScore) {
                $bestScore = $pct;
                $bestMatch = $id;
            }
        }

        return $bestMatch;
    }

    // ── Step 3: Import ──────────────────────────────────────────────────────

    public function import(): void
    {
        $companyId = Auth::user()->company_id;
        $imported  = 0;
        $skipped   = 0;
        $prepCreated = 0;

        // Cache for newly created suppliers
        $createdSuppliers = [];

        foreach ($this->rows as $row) {
            if ($row['skip']) {
                $skipped++;
                continue;
            }

            $pp       = $row['purchase_price'];
            $ps       = $row['pack_size'];
            $yp       = $row['yield_percent'];
            $baseCost = $pp / max($ps, 0.0001);
            $cost     = $yp > 0 ? $baseCost / ($yp / 100) : $baseCost;

            if (! empty($row['is_prep'])) {
                // ── Create Prep Item (placeholder Recipe + synced Ingredient) ──
                DB::transaction(function () use ($companyId, $row, $pp, $cost, &$prepCreated) {
                    $recipe = Recipe::create([
                        'company_id'             => $companyId,
                        'name'                   => $row['name'],
                        'code'                   => $row['code'],
                        'description'            => null,
                        'yield_quantity'         => 1,
                        'yield_uom_id'           => $row['base_uom_id'],
                        'selling_price'          => 0,
                        'cost_per_yield_unit'    => round($cost, 4),
                        'is_active'              => $row['is_active'],
                        'is_prep'                => true,
                        'ingredient_category_id' => $row['ingredient_category_id'],
                    ]);

                    Ingredient::create([
                        'company_id'             => $companyId,
                        'name'                   => $row['name'],
                        'code'                   => $row['code'],
                        'ingredient_category_id' => $row['ingredient_category_id'],
                        'base_uom_id'            => $row['base_uom_id'],
                        'recipe_uom_id'          => $row['base_uom_id'],
                        'purchase_price'         => 0,
                        'pack_size'              => 1,
                        'yield_percent'          => 100,
                        'current_cost'           => round($cost, 4),
                        'is_active'              => $row['is_active'],
                        'is_prep'                => true,
                        'prep_recipe_id'         => $recipe->id,
                    ]);

                    $prepCreated++;
                });
            } else {
                // ── Create regular Ingredient ──
                $ingredient = Ingredient::create([
                    'company_id'             => $companyId,
                    'name'                   => $row['name'],
                    'code'                   => $row['code'],
                    'ingredient_category_id' => $row['ingredient_category_id'],
                    'base_uom_id'            => $row['base_uom_id'],
                    'recipe_uom_id'          => $row['recipe_uom_id'],
                    'purchase_price'         => $pp,
                    'pack_size'              => $ps,
                    'yield_percent'          => $yp,
                    'current_cost'           => round($cost, 4),
                    'is_active'              => $row['is_active'],
                ]);

                // Resolve supplier
                $supplierId = $row['supplier_id'];
                if (! $supplierId && ! empty($row['supplier_is_new']) && ! empty($row['supplier_label'])) {
                    $supplierName = trim($row['supplier_label']);
                    $cacheKey = strtolower($supplierName);

                    if (isset($createdSuppliers[$cacheKey])) {
                        $supplierId = $createdSuppliers[$cacheKey];
                    } else {
                        $supplier = Supplier::create([
                            'company_id' => $companyId,
                            'name'       => $supplierName,
                            'is_active'  => true,
                        ]);
                        $supplierId = $supplier->id;
                        $createdSuppliers[$cacheKey] = $supplierId;
                    }
                }

                // Create supplier linkage
                if ($supplierId) {
                    SupplierIngredient::create([
                        'supplier_id'   => $supplierId,
                        'ingredient_id' => $ingredient->id,
                        'supplier_sku'  => null,
                        'last_cost'     => $pp,
                        'uom_id'        => $row['base_uom_id'],
                        'is_preferred'  => true,
                    ]);
                }
            }

            $imported++;
        }

        $this->importedCount    = $imported;
        $this->skippedCount     = $skipped;
        $this->prepCreatedCount = $prepCreated;
        $this->step             = 'done';
    }

    public function restart(): void
    {
        $this->file             = null;
        $this->step             = 'upload';
        $this->fileHeaders      = [];
        $this->fileDataRows     = [];
        $this->columnMapping    = [];
        $this->aiMapped         = false;
        $this->aiMapping        = false;
        $this->aiError          = '';
        $this->rows             = [];
        $this->totalRows        = 0;
        $this->validRows        = 0;
        $this->importedCount    = 0;
        $this->skippedCount     = 0;
        $this->prepCreatedCount = 0;
        $this->resetValidation();
    }

    public function backToMapping(): void
    {
        $this->step = 'mapping';
        $this->rows = [];
        $this->resetValidation();
    }

    public function downloadTemplate()
    {
        $headers = ['name', 'code', 'category', 'base_uom', 'recipe_uom', 'purchase_price', 'pack_size', 'yield_percent', 'is_active', 'supplier', 'type'];
        $sample  = [
            ['Chicken Breast', 'CHK-001', 'Food', 'kg', 'g', '12.50', '1', '80', 'yes', 'ABC Foods Sdn Bhd', 'ingredient'],
            ['Tomato Sauce', 'SAU-001', 'Food', 'L', 'ml', '', '', '', 'yes', '', 'prep'],
            ['Mineral Water', 'WTR-001', 'Beverage', 'bottle', 'bottle', '1.50', '1', '100', 'yes', '', 'ingredient'],
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($sample as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response()->streamDownload(
            fn () => print($csv),
            'ingredient_import_template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function render()
    {
        return view('livewire.ingredients.import')
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Import Ingredients']);
    }

    // ── Parsers ─────────────────────────────────────────────────────────────

    private function parseCsv(string $path): array
    {
        $reader = new CsvReader();
        $reader->open($path);

        $rows    = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(
                    fn ($c) => trim((string) $c->getValue()),
                    $row->getCells()
                );

                if ($headers === null) {
                    $headers = $cells;
                    continue;
                }

                if (array_filter($cells) === []) continue;

                $rows[] = array_combine(
                    $headers,
                    array_pad($cells, count($headers), '')
                );
            }
            break;
        }

        $reader->close();
        return ['headers' => $headers ?? [], 'rows' => $rows];
    }

    private function parseXlsx(string $path): array
    {
        $reader = new XlsxReader();
        $reader->open($path);

        $rows    = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(
                    fn ($c) => trim((string) $c->getValue()),
                    $row->getCells()
                );

                if ($headers === null) {
                    $headers = $cells;
                    continue;
                }

                if (array_filter($cells) === []) continue;

                $rows[] = array_combine(
                    $headers,
                    array_pad($cells, count($headers), '')
                );
            }
            break;
        }

        $reader->close();
        return ['headers' => $headers ?? [], 'rows' => $rows];
    }

    private function parseBool(string $val): bool
    {
        return in_array(strtolower(trim($val)), ['yes', '1', 'true', 'active', 'y']);
    }

    /**
     * Robustly decode JSON from AI responses that may contain control characters,
     * BOM, markdown fences, or other artifacts.
     */
    private function robustJsonDecode(string $raw): ?array
    {
        $content = trim($raw);

        // Remove BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Strip markdown code fences
        if (preg_match('/```(?:json)?\s*\n(.*)\n\s*```/s', $content, $m)) {
            $content = trim($m[1]);
        }

        // Extract JSON object if surrounded by other text
        if (! str_starts_with($content, '{') && ! str_starts_with($content, '[')) {
            if (preg_match('/(\{[\s\S]*\})\s*$/', $content, $m)) {
                $content = $m[1];
            }
        }

        // Attempt 1: direct decode
        $result = json_decode($content, true);
        if (is_array($result)) return $result;

        // Attempt 2: escape control characters inside JSON string values
        // Walk through the string character by character, tracking whether
        // we're inside a quoted string, and escape control chars within strings.
        $fixed = '';
        $inString = false;
        $escaped = false;
        $len = strlen($content);

        for ($i = 0; $i < $len; $i++) {
            $ch = $content[$i];
            $ord = ord($ch);

            if ($escaped) {
                $fixed .= $ch;
                $escaped = false;
                continue;
            }

            if ($ch === '\\' && $inString) {
                $fixed .= $ch;
                $escaped = true;
                continue;
            }

            if ($ch === '"') {
                $inString = ! $inString;
                $fixed .= $ch;
                continue;
            }

            if ($inString && $ord < 0x20) {
                // Replace control chars inside strings with their escaped form
                $fixed .= match ($ord) {
                    0x0A => '\\n',
                    0x0D => '\\r',
                    0x09 => '\\t',
                    default => '',
                };
                continue;
            }

            $fixed .= $ch;
        }

        $result = json_decode($fixed, true);
        if (is_array($result)) return $result;

        // Attempt 3: nuclear option — strip all control chars
        $stripped = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $content);
        $result = json_decode($stripped, true);
        if (is_array($result)) return $result;

        // Attempt 4: repair truncated JSON — AI may have hit token limit
        // Try on both $fixed and $stripped versions
        foreach ([$fixed, $stripped] as $candidate) {
            $repaired = $this->repairTruncatedJson($candidate);
            if ($repaired) {
                $result = json_decode($repaired, true);
                if (is_array($result)) {
                    Log::info('robustJsonDecode: repaired truncated JSON');
                    return $result;
                }
            }
        }

        Log::error('robustJsonDecode: all attempts failed', [
            'json_error' => json_last_error_msg(),
            'first200' => mb_substr($content, 0, 200),
            'last200' => mb_substr($content, -200),
        ]);

        return null;
    }

    /**
     * Attempt to repair truncated JSON by closing open brackets/braces.
     * Works for JSON that was cut off mid-way (e.g. AI hit max_tokens).
     */
    private function repairTruncatedJson(string $json): ?string
    {
        // Find the last complete item — look for the last "}," or "}" before truncation
        // Strategy: progressively trim from the end and try to close the structure

        // First, if we're in the middle of a string, close it
        $trimmed = $json;

        // Remove any trailing incomplete object/value
        // Find last complete object boundary: "}, {" or "}" followed by "]"
        $lastComplete = strrpos($trimmed, '},');
        if ($lastComplete === false) {
            $lastComplete = strrpos($trimmed, '}]');
        }

        if ($lastComplete === false) return null;

        // Cut at the last complete item
        $trimmed = substr($trimmed, 0, $lastComplete + 1);

        // Count open brackets/braces and close them
        $openBraces = substr_count($trimmed, '{') - substr_count($trimmed, '}');
        $openBrackets = substr_count($trimmed, '[') - substr_count($trimmed, ']');

        // Close open brackets/braces
        $trimmed .= str_repeat(']', max(0, $openBrackets));
        $trimmed .= str_repeat('}', max(0, $openBraces));

        return $trimmed;
    }
}
