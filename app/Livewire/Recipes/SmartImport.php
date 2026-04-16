<?php

namespace App\Livewire\Recipes;

use App\Models\AppSetting;
use App\Models\CentralKitchen;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\OutletGroup;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\UnitOfMeasure;
use App\Services\UomService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class SmartImport extends Component
{
    use WithFileUploads;

    public $file = null;
    public bool $isPrep = false;

    public string $step = 'upload'; // upload | mapping | preview | done

    public array $fileHeaders  = [];
    public array $fileDataRows = [];

    public array $columnMapping = [];
    public bool  $aiMapped      = false;
    public bool  $aiMapping      = false;
    public string $aiError       = '';

    public const SYSTEM_FIELDS = [
        'recipe_name'      => ['label' => 'Recipe Name',      'required' => true,  'description' => 'Name of the recipe/menu item'],
        'recipe_code'      => ['label' => 'Recipe Code',      'required' => false, 'description' => 'Internal code / SKU'],
        'category'         => ['label' => 'Menu Category',    'required' => false, 'description' => 'Menu category name'],
        'yield_quantity'   => ['label' => 'Yield Quantity',   'required' => false, 'description' => 'Number of servings per batch (default 1)'],
        'yield_uom'        => ['label' => 'Yield UOM',        'required' => false, 'description' => 'Yield unit of measure (portion, pcs, etc)'],
        'selling_price'    => ['label' => 'Selling Price',    'required' => false, 'description' => 'Selling price per serving'],
        'description'      => ['label' => 'Description',      'required' => false, 'description' => 'Recipe description or notes'],
        'ingredient_name'  => ['label' => 'Ingredient Name',  'required' => true,  'description' => 'Name of the ingredient used'],
        'quantity'         => ['label' => 'Quantity',          'required' => true,  'description' => 'Ingredient quantity per batch'],
        'uom'              => ['label' => 'UOM',               'required' => true,  'description' => 'Ingredient unit of measure (g, ml, pcs, etc)'],
        'waste_percentage' => ['label' => 'Waste %',           'required' => false, 'description' => 'Waste percentage (0-100)'],
    ];

    // Grouped recipe data for preview
    public array $recipes        = [];
    public int   $totalRecipes   = 0;
    public int   $validRecipes   = 0;
    public int   $importedCount  = 0;
    public int   $skippedCount   = 0;
    public int   $linesImported  = 0;

    // Global outlet tagging (applied to all imported recipes)
    public bool  $allOutlets     = true;
    public array $outletIds      = [];

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

    public function mount(): void
    {
        $this->isPrep = request()->query('type') === 'prep';
    }

    // ── Step 1: Upload & parse ────────────────────────────────────────────

    public function processUpload(): void
    {
        $this->validate();

        $path = $this->file->getRealPath();
        $ext  = strtolower($this->file->getClientOriginalExtension());

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

        $exactMapping = $this->tryExactMapping($this->fileHeaders);

        $hasName       = ! empty($exactMapping['recipe_name']);
        $hasIngredient = ! empty($exactMapping['ingredient_name']);

        if ($hasName && $hasIngredient) {
            $this->columnMapping = $exactMapping;
            $this->aiMapped      = false;
            $this->step          = 'mapping';
        } else {
            $this->step = 'mapping';
            $this->runAiMapping();
        }
    }

    // ── PDF extraction via AI vision ──────────────────────────────────────

    private function processPdfUpload(string $path): void
    {
        $apiKey = AppSetting::get('openrouter_api_key');
        if (! $apiKey) {
            $this->addError('file', 'PDF import requires an OpenRouter API key. Go to Settings > API Keys to configure it.');
            return;
        }

        $model = 'google/gemini-2.5-flash';

        $mimeType = mime_content_type($path) ?: 'application/pdf';
        $base64   = base64_encode(file_get_contents($path));
        $dataUri  = "data:{$mimeType};base64,{$base64}";

        $prompt = <<<'PROMPT'
Extract all items from this PDF document. It could be a restaurant MENU or a RECIPE CARD/BOOK — determine which type it is first.

Return a JSON object with this structure:
{
  "document_type": "menu" or "recipe_card",
  "recipes": [
    {
      "recipe_name": "Item/Recipe Name",
      "recipe_code": "code or null",
      "category": "menu category/section or null",
      "yield_quantity": 1,
      "yield_uom": "portion",
      "selling_price": 0.00,
      "description": "item description or null",
      "ingredients": []
    }
  ]
}

## CRITICAL: Determine document type first

**MENU** — a list of dishes/drinks with names, descriptions, and prices (like a customer-facing menu, price list, or catalogue). Clues: selling prices, short descriptions, no ingredient quantities.

**RECIPE CARD** — detailed preparation instructions with ingredient lists, quantities, and units. Clues: ingredient tables, gram/ml measurements, step-by-step instructions.

## Rules based on document type:

### If MENU:
- Extract every menu item: name, description, price, and category/section
- Set "ingredients" to an EMPTY array [] — do NOT guess or infer ingredients from the description
- Capture the description as-is from the menu (e.g. "Grilled chicken with sambal, served with rice and salad")
- Extract the selling price — this is the most important field for menus
- Use categories/sections visible in the menu (Appetizers, Mains, Beverages, etc.)

### If RECIPE CARD:
- Extract every recipe with its FULL ingredient list
- Include ingredient_name, quantity, uom, waste_percentage for each ingredient
- Use standard UOM abbreviations: g, kg, ml, L, pcs, tbsp, tsp, cup, etc.
- Clean ingredient names: remove quantities/units from the name itself
- If yield/servings info is present, extract it; otherwise default to 1 portion
- waste_percentage defaults to 0 unless explicitly stated

## General rules:
- Extract EVERY item found in the document
- If a selling price is shown, extract it; otherwise default to 0
- Use numeric values for quantity, yield_quantity, selling_price, waste_percentage

IMPORTANT: Return ONLY valid JSON. No markdown, no explanation — just the JSON object.
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
                $msg = $body['error']['message'] ?? $body['message'] ?? ('HTTP ' . $response->status());
                throw new \RuntimeException($msg);
            }

            $data    = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';
            $result  = $this->robustJsonDecode($content);

            $recipeItems = null;
            if (is_array($result)) {
                $recipeItems = $result['recipes'] ?? $result['data'] ?? $result['items'] ?? null;
                if (! $recipeItems && isset($result[0]) && is_array($result[0])) {
                    $recipeItems = $result;
                }
                if (! $recipeItems) {
                    foreach ($result as $val) {
                        if (is_array($val) && ! empty($val) && isset($val[0]) && is_array($val[0])) {
                            $recipeItems = $val;
                            break;
                        }
                    }
                }
            }

            if (empty($recipeItems)) {
                throw new \RuntimeException('AI could not extract any recipes from this PDF.');
            }

            // Convert to flat CSV-like rows for the same preview pipeline
            $this->fileHeaders  = array_keys(self::SYSTEM_FIELDS);
            $this->fileDataRows = [];

            $docType = $result['document_type'] ?? 'recipe_card';

            foreach ($recipeItems as $recipe) {
                $ingredients = $recipe['ingredients'] ?? $recipe['lines'] ?? $recipe['items'] ?? [];

                $baseRow = [
                    'recipe_name'      => (string) ($recipe['recipe_name'] ?? $recipe['name'] ?? ''),
                    'recipe_code'      => (string) ($recipe['recipe_code'] ?? $recipe['code'] ?? ''),
                    'category'         => (string) ($recipe['category'] ?? ''),
                    'yield_quantity'   => (string) ($recipe['yield_quantity'] ?? '1'),
                    'yield_uom'        => (string) ($recipe['yield_uom'] ?? 'portion'),
                    'selling_price'    => (string) ($recipe['selling_price'] ?? '0'),
                    'description'      => (string) ($recipe['description'] ?? ''),
                    'ingredient_name'  => '',
                    'quantity'         => '',
                    'uom'              => '',
                    'waste_percentage' => '0',
                ];

                if (empty($ingredients)) {
                    // Menu item with no ingredients — still create the row
                    $this->fileDataRows[] = $baseRow;
                } else {
                    foreach ($ingredients as $ing) {
                        $this->fileDataRows[] = array_merge($baseRow, [
                            'ingredient_name'  => (string) ($ing['ingredient_name'] ?? $ing['name'] ?? ''),
                            'quantity'         => (string) ($ing['quantity'] ?? '0'),
                            'uom'              => (string) ($ing['uom'] ?? $ing['unit'] ?? ''),
                            'waste_percentage' => (string) ($ing['waste_percentage'] ?? '0'),
                        ]);
                    }
                }
            }

            // Auto-map (PDF output matches our system field keys)
            $this->columnMapping = [];
            foreach ($this->fileHeaders as $key) {
                $this->columnMapping[$key] = $key;
            }

            $this->aiMapped = true;
            $this->step     = 'mapping';

        } catch (\Throwable $e) {
            set_time_limit((int) $previousTimeout ?: 60);
            Log::error('PDF recipe extraction failed: ' . $e->getMessage());
            $this->addError('file', 'Failed to extract recipes from PDF: ' . $e->getMessage());
        }
    }

    // ── Exact header matching ─────────────────────────────────────────────

    private function tryExactMapping(array $headers): array
    {
        $mapping     = [];
        $systemKeys  = array_keys(self::SYSTEM_FIELDS);

        $normalizedMap = [];
        foreach ($headers as $header) {
            $normalized = strtolower(trim(str_replace([' ', '-', '_'], '_', $header)));
            $normalizedMap[$normalized] = $header;
        }

        $aliases = [
            'recipe_name'      => ['recipe_name', 'recipe', 'menu_item', 'item_name', 'dish', 'dish_name', 'name'],
            'recipe_code'      => ['recipe_code', 'code', 'sku', 'item_code'],
            'category'         => ['category', 'menu_category', 'section', 'group'],
            'yield_quantity'   => ['yield_quantity', 'yield_qty', 'yield', 'servings', 'batch_size', 'portions'],
            'yield_uom'        => ['yield_uom', 'yield_unit', 'serving_unit', 'portion_unit'],
            'selling_price'    => ['selling_price', 'price', 'sell_price', 'menu_price'],
            'description'      => ['description', 'notes', 'remarks'],
            'ingredient_name'  => ['ingredient_name', 'ingredient', 'raw_material', 'material', 'item'],
            'quantity'         => ['quantity', 'qty', 'amount'],
            'uom'              => ['uom', 'unit', 'unit_of_measure'],
            'waste_percentage' => ['waste_percentage', 'waste_%', 'waste', 'waste_pct', 'wastage'],
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

    // ── AI Column Mapping ─────────────────────────────────────────────────

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

        $sampleRows = array_slice($this->fileDataRows, 0, 5);
        $sampleText = '';
        foreach ($sampleRows as $i => $row) {
            $sampleText .= "Row " . ($i + 1) . ": " . json_encode($row) . "\n";
        }

        $systemFields = [];
        foreach (self::SYSTEM_FIELDS as $key => $info) {
            $systemFields[] = "{$key} ({$info['description']}" . ($info['required'] ? ', REQUIRED' : '') . ")";
        }

        $prompt = "You are a data column mapper for a recipe management system.\n\n"
            . "The user uploaded a spreadsheet where each row represents one ingredient line of a recipe. "
            . "Multiple rows with the same recipe name form one complete recipe.\n\n"
            . "File column headers:\n" . json_encode($this->fileHeaders) . "\n\n"
            . "Sample data rows:\n{$sampleText}\n"
            . "Map each file column to the best matching system field. System fields:\n"
            . implode("\n", $systemFields) . "\n\n"
            . "Return a JSON object where keys are system field names and values are the EXACT original file column header strings. "
            . "Only include mappings you are confident about. If a column doesn't match, omit it. "
            . "'recipe_name', 'ingredient_name', 'quantity', and 'uom' are required — try hard to find matches.\n\n"
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

            $body    = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? '';
            $mapped  = json_decode($content, true);

            if (! is_array($mapped)) {
                throw new \RuntimeException('Invalid response format from AI');
            }

            $validMapping = [];
            $headerLower  = array_map('strtolower', $this->fileHeaders);

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
            Log::warning('AI recipe column mapping failed: ' . $e->getMessage());
            $this->columnMapping = $this->tryExactMapping($this->fileHeaders);
            $this->aiMapped      = false;
            $this->aiError       = 'AI mapping failed: ' . $e->getMessage() . '. Using basic column matching.';
        }

        $this->aiMapping = false;
    }

    // ── Step 2: Confirm mapping & build preview ───────────────────────────

    public function confirmMapping(): void
    {
        if (empty($this->columnMapping['recipe_name'])) {
            $this->addError('mapping', 'The "Recipe Name" field must be mapped.');
            return;
        }

        $this->buildPreview();
        $this->step = 'preview';
    }

    private const UOM_ALIASES = [
        'liter' => 'l', 'litre' => 'l', 'lit' => 'l', 'ltr' => 'l',
        'milliliter' => 'ml', 'millilitre' => 'ml',
        'gram' => 'g', 'gm' => 'g', 'grm' => 'g', 'gms' => 'g', 'grams' => 'g',
        'kilogram' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg', 'kgs' => 'kg',
        'milligram' => 'mg', 'milligrams' => 'mg',
        'piece' => 'pcs', 'pieces' => 'pcs', 'pc' => 'pcs', 'each' => 'pcs', 'ea' => 'pcs', 'unit' => 'pcs',
        'dozen' => 'doz', 'dozens' => 'doz',
        'tablespoon' => 'tbsp', 'tablespoons' => 'tbsp',
        'teaspoon' => 'tsp', 'teaspoons' => 'tsp',
        'cup' => 'cup', 'cups' => 'cup',
        'portion' => 'portion', 'portions' => 'portion', 'serving' => 'portion', 'servings' => 'portion', 'srv' => 'portion',
        'carton' => 'ctn', 'packet' => 'pkt', 'bottle' => 'bottle', 'btl' => 'bottle',
        'pack' => 'pack', 'packs' => 'pack',
        'slice' => 'slice', 'slices' => 'slice',
    ];

    private function resolveUom(string $raw): ?int
    {
        if (! $raw) return null;

        static $uomsByAbbr = null, $uomsByName = null;
        if ($uomsByAbbr === null) {
            $uomsByAbbr = UnitOfMeasure::all()->mapWithKeys(fn ($u) => [strtolower($u->abbreviation) => $u->id]);
            $uomsByName = UnitOfMeasure::all()->mapWithKeys(fn ($u) => [strtolower($u->name) => $u->id]);
        }

        $key = strtolower(trim($raw));

        if (isset($uomsByAbbr[$key])) return $uomsByAbbr[$key];
        if (isset($uomsByName[$key])) return $uomsByName[$key];

        $alias = self::UOM_ALIASES[$key] ?? null;
        if ($alias && isset($uomsByAbbr[$alias])) return $uomsByAbbr[$alias];

        return null;
    }

    private function matchIngredient(string $name, $ingredientsByName): ?array
    {
        if (! $name) return null;

        $key = strtolower(trim($name));

        // Exact match
        if (isset($ingredientsByName[$key])) {
            $ing = $ingredientsByName[$key];
            return ['id' => $ing->id, 'name' => $ing->name, 'confidence' => 100];
        }

        // Fuzzy match
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

    private function parseNumber(string $raw, float $default = 0.0): float
    {
        if (! $raw) return $default;
        $cleaned = preg_replace('/[^\d.\-]/', '', str_replace(',', '', $raw));
        return is_numeric($cleaned) ? (float) $cleaned : $default;
    }

    private function buildPreview(): void
    {
        $companyId = Auth::user()->company_id;

        $ingredientsByName = Ingredient::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($i) => strtolower($i->name));

        $mapping = $this->columnMapping;

        $getValue = function (array $raw, string $sysField) use ($mapping): string {
            $header = $mapping[$sysField] ?? null;
            if (! $header) return '';
            return trim($raw[$header] ?? '');
        };

        // Group rows by recipe name
        $grouped = [];

        foreach ($this->fileDataRows as $raw) {
            $recipeName = $getValue($raw, 'recipe_name');
            if (! $recipeName) continue;

            $recipeKey = strtolower($recipeName);

            if (! isset($grouped[$recipeKey])) {
                $yieldUomRaw = $getValue($raw, 'yield_uom');
                $yieldUomId  = $this->resolveUom($yieldUomRaw ?: 'portion');

                $grouped[$recipeKey] = [
                    'name'           => strtoupper($recipeName),
                    'code'           => $getValue($raw, 'recipe_code') ?: null,
                    'category'       => $getValue($raw, 'category') ?: null,
                    'yield_quantity' => max(0.0001, $this->parseNumber($getValue($raw, 'yield_quantity'), 1)),
                    'yield_uom_id'   => $yieldUomId,
                    'yield_uom_label'=> $yieldUomRaw ?: 'portion',
                    'selling_price'  => $this->parseNumber($getValue($raw, 'selling_price'), 0),
                    'description'    => $getValue($raw, 'description') ?: null,
                    'lines'          => [],
                    'errors'         => [],
                    'skip'           => false,
                ];

                if (! $yieldUomId) {
                    $grouped[$recipeKey]['errors'][] = 'Yield UOM "' . $yieldUomRaw . '" not found';
                }
            }

            // Parse ingredient line
            $ingName = $getValue($raw, 'ingredient_name');
            if (! $ingName) continue;

            $uomRaw = $getValue($raw, 'uom');
            $uomId  = $this->resolveUom($uomRaw);

            $match = $this->matchIngredient($ingName, $ingredientsByName);

            $lineErrors = [];
            if (! $match) {
                $lineErrors[] = 'Ingredient "' . $ingName . '" not found in system';
            }
            if (! $uomId && $uomRaw) {
                $lineErrors[] = 'UOM "' . $uomRaw . '" not found';
            }

            $grouped[$recipeKey]['lines'][] = [
                'ingredient_name'  => $ingName,
                'ingredient_id'    => $match['id'] ?? null,
                'matched_name'     => $match['name'] ?? null,
                'confidence'       => $match['confidence'] ?? 0,
                'quantity'         => max(0.0001, $this->parseNumber($getValue($raw, 'quantity'), 1)),
                'uom_raw'          => $uomRaw,
                'uom_id'           => $uomId,
                'waste_percentage' => min(100, max(0, $this->parseNumber($getValue($raw, 'waste_percentage'), 0))),
                'errors'           => $lineErrors,
            ];
        }

        // Determine skip status per recipe
        foreach ($grouped as &$recipe) {
            $hasUnmatched = false;
            foreach ($recipe['lines'] as $line) {
                if (! $line['ingredient_id'] || ! $line['uom_id']) {
                    $hasUnmatched = true;
                    break;
                }
            }
            if ($hasUnmatched || ! empty($recipe['errors'])) {
                $recipe['skip'] = true;
            }
        }
        unset($recipe);

        $this->recipes      = array_values($grouped);
        $this->totalRecipes = count($this->recipes);
        $this->validRecipes = collect($this->recipes)->where('skip', false)->count();
    }

    // ── Fix ingredient match ──────────────────────────────────────────────

    public function fixIngredient(int $recipeIdx, int $lineIdx, int $ingredientId): void
    {
        if (! isset($this->recipes[$recipeIdx]['lines'][$lineIdx])) return;

        $ingredient = Ingredient::find($ingredientId);
        if (! $ingredient) return;

        $this->recipes[$recipeIdx]['lines'][$lineIdx]['ingredient_id']   = $ingredient->id;
        $this->recipes[$recipeIdx]['lines'][$lineIdx]['matched_name']    = $ingredient->name;
        $this->recipes[$recipeIdx]['lines'][$lineIdx]['confidence']      = 100;
        $this->recipes[$recipeIdx]['lines'][$lineIdx]['errors']          = array_filter(
            $this->recipes[$recipeIdx]['lines'][$lineIdx]['errors'],
            fn ($e) => ! str_starts_with($e, 'Ingredient')
        );

        $this->recalcRecipeSkip($recipeIdx);
    }

    public function fixLineUom(int $recipeIdx, int $lineIdx, int $uomId): void
    {
        if (! isset($this->recipes[$recipeIdx]['lines'][$lineIdx])) return;

        $this->recipes[$recipeIdx]['lines'][$lineIdx]['uom_id'] = $uomId;
        $this->recipes[$recipeIdx]['lines'][$lineIdx]['errors']  = array_filter(
            $this->recipes[$recipeIdx]['lines'][$lineIdx]['errors'],
            fn ($e) => ! str_starts_with($e, 'UOM')
        );

        $this->recalcRecipeSkip($recipeIdx);
    }

    public function fixYieldUom(int $recipeIdx, int $uomId): void
    {
        if (! isset($this->recipes[$recipeIdx])) return;

        $this->recipes[$recipeIdx]['yield_uom_id'] = $uomId;
        $this->recipes[$recipeIdx]['errors'] = array_filter(
            $this->recipes[$recipeIdx]['errors'],
            fn ($e) => ! str_starts_with($e, 'Yield UOM')
        );

        $this->recalcRecipeSkip($recipeIdx);
    }

    public function toggleSkip(int $recipeIdx): void
    {
        if (! isset($this->recipes[$recipeIdx])) return;
        $this->recipes[$recipeIdx]['skip'] = ! $this->recipes[$recipeIdx]['skip'];
        $this->validRecipes = collect($this->recipes)->where('skip', false)->count();
    }

    private function recalcRecipeSkip(int $recipeIdx): void
    {
        $recipe = &$this->recipes[$recipeIdx];
        $hasIssue = ! empty($recipe['errors']);

        if (! $hasIssue) {
            foreach ($recipe['lines'] as $line) {
                if (! $line['ingredient_id'] || ! $line['uom_id']) {
                    $hasIssue = true;
                    break;
                }
            }
        }

        $recipe['skip'] = $hasIssue;
        $this->validRecipes = collect($this->recipes)->where('skip', false)->count();
    }

    // ── Outlet tagging ──────────────────────────────────────────────────

    public function applyGroup(int $groupId): void
    {
        $group = OutletGroup::with('outlets')->find($groupId);
        if (! $group) return;

        $centralKitchenOutletIds = CentralKitchen::whereNotNull('outlet_id')->pluck('outlet_id')->all();
        $groupOutletIds = $group->outlets
            ->pluck('id')
            ->reject(fn ($id) => in_array($id, $centralKitchenOutletIds))
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($groupOutletIds)) return;

        $this->allOutlets = false;
        $existing = array_map('intval', $this->outletIds);
        $this->outletIds = array_values(array_unique(array_merge($existing, $groupOutletIds)));
    }

    public function clearOutletSelection(): void
    {
        $this->outletIds = [];
    }

    // ── Step 3: Import ────────────────────────────────────────────────────

    public function import(): void
    {
        $user = Auth::user();
        if ($user?->company?->recipes_locked && ! $user->canBypassLock()) {
            session()->flash('error', 'Recipes are locked. Ask a company admin to unlock in Settings → Company Details.');
            return;
        }

        $companyId = $user->company_id;
        $uomService = app(UomService::class);
        $imported = 0;
        $skipped  = 0;
        $lines    = 0;

        foreach ($this->recipes as $recipeData) {
            if ($recipeData['skip']) {
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($recipeData, $companyId, $uomService, &$imported, &$lines) {
                $recipe = Recipe::create([
                    'company_id'          => $companyId,
                    'name'                => trim($recipeData['name']) ?: 'Untitled',
                    'code'                => trim($recipeData['code'] ?? '') ?: null,
                    'description'         => trim($recipeData['description'] ?? '') ?: null,
                    'category'            => trim($recipeData['category'] ?? '') ?: null,
                    'yield_quantity'      => max(0.0001, floatval($recipeData['yield_quantity'] ?? 1)),
                    'yield_uom_id'        => $recipeData['yield_uom_id'] ?: null,
                    'selling_price'       => floatval($recipeData['selling_price'] ?? 0),
                    'cost_per_yield_unit' => 0,
                    'is_active'           => true,
                    'is_prep'             => $this->isPrep,
                ]);

                $totalCost = 0;

                foreach ($recipeData['lines'] as $idx => $line) {
                    if (! $line['ingredient_id'] || ! $line['uom_id']) continue;

                    $recipe->lines()->create([
                        'ingredient_id'    => $line['ingredient_id'],
                        'quantity'         => max(0.0001, floatval($line['quantity'] ?? 0)),
                        'uom_id'           => $line['uom_id'],
                        'waste_percentage' => min(100, max(0, floatval($line['waste_percentage'] ?? 0))),
                        'sort_order'       => $idx,
                        'is_packaging'     => false,
                    ]);

                    $ingredient = Ingredient::with(['baseUom', 'uomConversions'])->find($line['ingredient_id']);
                    $uom = UnitOfMeasure::find($line['uom_id']);
                    if ($ingredient && $uom) {
                        $costPerUom  = $uomService->convertCost($ingredient, $uom);
                        $wasteFactor = 1 + ($line['waste_percentage'] / 100);
                        $totalCost  += $costPerUom * $wasteFactor * $line['quantity'];
                    }

                    $lines++;
                }

                $yieldQty = max($recipeData['yield_quantity'], 0.0001);
                $recipe->update(['cost_per_yield_unit' => round($totalCost / $yieldQty, 4)]);

                // Sync outlet tags
                if (! $this->allOutlets && ! empty($this->outletIds)) {
                    $recipe->outlets()->sync(array_map('intval', $this->outletIds));
                }

                $imported++;
            });
        }

        $this->importedCount = $imported;
        $this->skippedCount  = $skipped;
        $this->linesImported = $lines;
        $this->step          = 'done';
    }

    public function restart(): void
    {
        $this->file          = null;
        $this->step          = 'upload';
        $this->fileHeaders   = [];
        $this->fileDataRows  = [];
        $this->columnMapping = [];
        $this->aiMapped      = false;
        $this->aiMapping     = false;
        $this->aiError       = '';
        $this->recipes       = [];
        $this->totalRecipes  = 0;
        $this->validRecipes  = 0;
        $this->importedCount = 0;
        $this->skippedCount  = 0;
        $this->linesImported = 0;
        $this->allOutlets    = true;
        $this->outletIds     = [];
        $this->resetValidation();
    }

    public function downloadTemplate()
    {
        $headers = ['recipe_name', 'recipe_code', 'category', 'yield_quantity', 'yield_uom', 'selling_price', 'ingredient_name', 'quantity', 'uom', 'waste_percentage'];

        $sample = [
            ['Nasi Lemak', 'NL-001', 'Food', '1', 'portion', '12.90', 'Rice', '200', 'g', '5'],
            ['Nasi Lemak', 'NL-001', 'Food', '1', 'portion', '12.90', 'Coconut Milk', '100', 'ml', '0'],
            ['Nasi Lemak', 'NL-001', 'Food', '1', 'portion', '12.90', 'Sambal', '30', 'g', '0'],
            ['Teh Tarik', 'TT-001', 'Beverage', '1', 'cup', '3.50', 'Tea', '10', 'g', '0'],
            ['Teh Tarik', 'TT-001', 'Beverage', '1', 'cup', '3.50', 'Condensed Milk', '30', 'ml', '0'],
        ];

        $filename = $this->isPrep ? 'prep_item_smart_import_template.csv' : 'recipe_smart_import_template.csv';

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
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function render()
    {
        $title = $this->isPrep ? 'Smart Import Prep Items' : 'Smart Import Recipes';

        $uoms = UnitOfMeasure::orderBy('name')->get();

        $ingredients = [];
        $recipeCategories = [];
        if ($this->step === 'preview') {
            $ingredients = Ingredient::where('company_id', Auth::user()->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->select('id', 'name')
                ->get();

            $recipeCategories = RecipeCategory::with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
                ->roots()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        $centralKitchenOutletIds = CentralKitchen::whereNotNull('outlet_id')->pluck('outlet_id')->filter()->all();

        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->whereNotIn('id', $centralKitchenOutletIds)
            ->orderBy('name')
            ->get();

        $outletGroups = OutletGroup::with('outlets')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function ($g) use ($centralKitchenOutletIds) {
                $ids = $g->outlets->pluck('id')
                    ->reject(fn ($id) => in_array($id, $centralKitchenOutletIds))
                    ->values()
                    ->all();
                return (object) ['id' => $g->id, 'name' => $g->name, 'outlet_ids' => $ids];
            })
            ->filter(fn ($g) => count($g->outlet_ids) > 0)
            ->values();

        return view('livewire.recipes.smart-import', compact('uoms', 'ingredients', 'recipeCategories', 'outlets', 'outletGroups'))
            ->layout('layouts.app', ['title' => $title]);
    }

    // ── Parsers ───────────────────────────────────────────────────────────

    private function parseCsv(string $path): array
    {
        $reader = new CsvReader();
        $reader->open($path);

        $rows = [];
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
        return ['headers' => $headers ?? [], 'rows' => $rows];
    }

    private function parseXlsx(string $path): array
    {
        $reader = new XlsxReader();
        $reader->open($path);

        $rows = [];
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
        return ['headers' => $headers ?? [], 'rows' => $rows];
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

        $start = strpos($raw, '[');
        $end   = strrpos($raw, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);
            if (is_array($decoded)) return $decoded;
        }

        return null;
    }
}
