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
        'pack_size'      => ['label' => 'Pack Size',       'required' => false, 'description' => 'Pack size in recipe UOM per base UOM'],
        'yield_percent'  => ['label' => 'Yield %',         'required' => false, 'description' => 'Yield percentage (0-100)'],
        'is_active'      => ['label' => 'Active',          'required' => false, 'description' => 'Active status (yes/no)'],
        'supplier'       => ['label' => 'Default Supplier','required' => false, 'description' => 'Preferred supplier name'],
        'type'           => ['label' => 'Type',            'required' => false, 'description' => 'ingredient or prep (default: auto-detect)'],
        'remark'         => ['label' => 'Remark',          'required' => false, 'description' => 'Packaging info / notes from AI'],
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
Extract all ingredient/item data from this PDF document. This is a supplier product/price list for a restaurant.

Return a JSON object with this structure:
{
  "items": [
    {
      "name": "clean item name",
      "code": "item code/SKU or null",
      "category": "category or null",
      "base_uom": "purchasing unit",
      "recipe_uom": "recipe unit",
      "pack_size": 0,
      "purchase_price": 0.00,
      "yield_percent": 100,
      "is_active": "yes",
      "supplier": "supplier name or null",
      "type": "ingredient",
      "remark": "packaging description"
    }
  ]
}

## CRITICAL: Smart packaging detection from item names

Read the item name carefully to understand the product packaging and set the correct UOMs and pack_size.

### Understanding base_uom vs recipe_uom vs pack_size:
- "base_uom" = how the item is PURCHASED from supplier (box, ctn, pail, bag, bottle, tin, pkt, tray, kg, etc.)
- "recipe_uom" = the SMALLER unit the kitchen uses in recipes (g, ml, pcs, kg, L, etc.)
- "pack_size" = how many recipe_uom units are in 1 base_uom. Set to 0 if you cannot determine the exact quantity (user will update later).

### Examples of parsing item names:

LIQUIDS with volume:
- "SOUR CREAM 1 LIT" → name: "Sour Cream", base_uom: "box", recipe_uom: "ml", pack_size: 1000, remark: "1 Liter per box"
- "COOKING OIL 5 LIT" → name: "Cooking Oil", base_uom: "pail", recipe_uom: "ml", pack_size: 5000, remark: "5 Liter per pail"
- "COCONUT MILK 1 LIT" → name: "Coconut Milk", base_uom: "box", recipe_uom: "ml", pack_size: 1000, remark: "1 Liter per box"
- "OLIVE OIL 250ML" → name: "Olive Oil", base_uom: "bottle", recipe_uom: "ml", pack_size: 250, remark: "250ml per bottle"
- "SOY SAUCE 1.6 LIT" → name: "Soy Sauce", base_uom: "bottle", recipe_uom: "ml", pack_size: 1600, remark: "1.6 Liter per bottle"

SOLIDS with weight:
- "SUGAR 1KG" → name: "Sugar", base_uom: "bag", recipe_uom: "g", pack_size: 1000, remark: "1kg per bag"
- "FLOUR 25KG" → name: "Flour", base_uom: "bag", recipe_uom: "g", pack_size: 25000, remark: "25kg per bag"
- "BUTTER 250G" → name: "Butter", base_uom: "box", recipe_uom: "g", pack_size: 250, remark: "250g per box"
- "TOMATO SAUCE 340G" → name: "Tomato Sauce", base_uom: "bottle", recipe_uom: "g", pack_size: 340, remark: "340g per bottle"
- "MOZZARELLA CHEESE 2KG" → name: "Mozzarella Cheese", base_uom: "bag", recipe_uom: "g", pack_size: 2000, remark: "2kg per bag"

COUNTED items:
- "EGG 30'S" → name: "Egg", base_uom: "tray", recipe_uom: "pcs", pack_size: 30, remark: "30 pcs per tray"
- "EGG 10 PCS" → name: "Egg", base_uom: "pack", recipe_uom: "pcs", pack_size: 10, remark: "10 pcs per pack"
- "PLASTIC BAG 100'S" → name: "Plastic Bag", base_uom: "pack", recipe_uom: "pcs", pack_size: 100, remark: "100 pcs per pack"

SEAFOOD with size grading (e.g. "6-8" means 6-8 pieces per kg):
- "FRESH WATER PRAWN 6-8" → name: "Fresh Water Prawn 6-8", base_uom: "kg", recipe_uom: "pcs", pack_size: 0, remark: "Size 6-8 (approx 6-8 pcs per kg)"
- "TIGER PRAWN 16/20" → name: "Tiger Prawn 16/20", base_uom: "kg", recipe_uom: "pcs", pack_size: 0, remark: "Size 16/20 (approx 16-20 pcs per kg)"
- "SQUID 10-12" → name: "Squid 10-12", base_uom: "kg", recipe_uom: "pcs", pack_size: 0, remark: "Size 10-12 (approx 10-12 pcs per kg)"
Note: For seafood size gradings, KEEP the size in the name and set pack_size to 0 (user will decide).

BULK/RAW items without packaging info:
- "CHICKEN BREAST" → name: "Chicken Breast", base_uom: "kg", recipe_uom: "g", pack_size: 1000, remark: "per kg"
- "ONION" → name: "Onion", base_uom: "kg", recipe_uom: "g", pack_size: 1000, remark: "per kg"
- "GARLIC" → name: "Garlic", base_uom: "kg", recipe_uom: "g", pack_size: 1000, remark: "per kg"

CANNED items:
- "TUNA IN CAN" → name: "Tuna In Can", base_uom: "tin", recipe_uom: "g", pack_size: 0, remark: "per tin (size unknown)"
- "LYCHEE IN CAN" → name: "Lychee In Can", base_uom: "tin", recipe_uom: "g", pack_size: 0, remark: "per tin (size unknown)"
- "TOMATO PASTE 400G" → name: "Tomato Paste", base_uom: "tin", recipe_uom: "g", pack_size: 400, remark: "400g per tin"

### Other rules:
- CLEAN the item name: remove weight/volume info (1KG, 500ML, 1 LIT, 340G) but KEEP size gradings for seafood (6-8, 16/20)
- Extract ACTUAL prices from the document — never default to 0 if a price is shown
- If a supplier name appears in the document header/title, use it for all items
- For type: almost everything is "ingredient". Only use "prep" if clearly made in-house (very rare in supplier lists)
- If pack_size cannot be determined from the name, set it to 0 (not 1)
- Use numeric values for prices, pack_size, yield_percent
- "remark" should describe the packaging in human-readable format

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
                    $val = $item[$key] ?? null;
                    if (is_null($val)) {
                        $row[$key] = '';
                    } elseif (is_numeric($val)) {
                        $row[$key] = (string) $val;
                    } elseif (is_scalar($val)) {
                        $row[$key] = (string) $val;
                    } else {
                        $row[$key] = '';
                    }
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

    private const UOM_ALIASES = [
        'liter' => 'l', 'litre' => 'l', 'lit' => 'l', 'ltr' => 'l',
        'milliliter' => 'ml', 'millilitre' => 'ml', 'milli liter' => 'ml',
        'gram' => 'g', 'gm' => 'g', 'grm' => 'g', 'gms' => 'g', 'grams' => 'g',
        'kilogram' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg', 'kgs' => 'kg',
        'milligram' => 'mg', 'milligrams' => 'mg',
        'piece' => 'pcs', 'pieces' => 'pcs', 'pc' => 'pcs', 'each' => 'pcs', 'ea' => 'pcs', 'unit' => 'pcs', 'units' => 'pcs',
        'dozen' => 'doz', 'dozens' => 'doz',
        'carton' => 'ctn', 'cartons' => 'ctn',
        'packet' => 'pkt', 'packets' => 'pkt', 'sachet' => 'pkt',
        'bottle' => 'bottle', 'bottles' => 'bottle', 'btl' => 'bottle',
        'tin' => 'can', 'tins' => 'can', 'cans' => 'can',
        'bags' => 'bag', 'sack' => 'bag',
        'pails' => 'pail', 'bucket' => 'pail',
        'boxes' => 'box', 'bx' => 'box',
        'tray' => 'tray', 'trays' => 'tray',
        'pack' => 'pack', 'packs' => 'pack', 'pk' => 'pack',
        'ounce' => 'oz', 'ounces' => 'oz',
        'pound' => 'lb', 'pounds' => 'lb', 'lbs' => 'lb',
        'gallon' => 'gal', 'gallons' => 'gal',
        'fluid ounce' => 'fl oz', 'fl. oz' => 'fl oz', 'fl.oz' => 'fl oz',
        'meter' => 'm', 'meters' => 'm', 'metre' => 'm',
        'centimeter' => 'cm', 'centimeters' => 'cm', 'centimetre' => 'cm',
        'lots' => 'lot',
    ];

    // Conversion multipliers to the smallest unit (for pack_size calculation)
    private const UOM_CONVERSIONS = [
        'l' => ['to' => 'ml', 'factor' => 1000],
        'lit' => ['to' => 'ml', 'factor' => 1000],
        'liter' => ['to' => 'ml', 'factor' => 1000],
        'litre' => ['to' => 'ml', 'factor' => 1000],
        'ltr' => ['to' => 'ml', 'factor' => 1000],
        'kg' => ['to' => 'g', 'factor' => 1000],
        'kilo' => ['to' => 'g', 'factor' => 1000],
        'kilogram' => ['to' => 'g', 'factor' => 1000],
    ];

    /**
     * Resolve a UOM string to an ID.
     * Handles: plain UOM, aliases, parenthetical "G (Gram)", and quantity+UOM "1000 ML".
     * Returns [uom_id, extracted_pack_size] — pack_size is null if no quantity was found.
     */
    private function resolveUomWithQty(string $raw, $uomsByAbbr, $uomsByName): array
    {
        if (! $raw) return [null, null];

        $key = strtolower(trim($raw));

        // Try direct match first
        $id = $this->matchUom($key, $uomsByAbbr, $uomsByName);
        if ($id) return [$id, null];

        // Strip parenthetical like "G (Gram)" → try "g"
        $stripped = preg_replace('/\s*\(.*\)/', '', $key);
        $stripped = trim($stripped);
        if ($stripped !== $key) {
            $id = $this->matchUom($stripped, $uomsByAbbr, $uomsByName);
            if ($id) return [$id, null];
        }

        // Try to extract quantity + UOM from strings like "1000 ML", "1.5 L", "250 G", "1 LIT"
        if (preg_match('/^([\d.,]+)\s*([a-zA-Z]+.*)$/', trim($raw), $m)) {
            $qty     = (float) str_replace(',', '', $m[1]);
            $uomPart = strtolower(trim($m[2]));

            $id = $this->matchUom($uomPart, $uomsByAbbr, $uomsByName);
            if ($id) {
                return [$id, $qty]; // e.g. "1000 ML" → ml ID, pack_size=1000
            }

            // Check if UOM has a conversion (e.g. "1 LIT" → resolve to ml, pack_size = 1*1000)
            $resolvedAlias = self::UOM_ALIASES[$uomPart] ?? $uomPart;
            if (isset(self::UOM_CONVERSIONS[$uomPart]) || isset(self::UOM_CONVERSIONS[$resolvedAlias])) {
                $conv = self::UOM_CONVERSIONS[$uomPart] ?? self::UOM_CONVERSIONS[$resolvedAlias] ?? null;
                if ($conv) {
                    $targetId = $uomsByAbbr[$conv['to']] ?? null;
                    if ($targetId) {
                        return [$targetId, $qty * $conv['factor']]; // e.g. "1 LIT" → ml, 1000
                    }
                }
            }

            // Try the UOM part through aliases even if no conversion
            $aliasAbbr = self::UOM_ALIASES[$uomPart] ?? null;
            if ($aliasAbbr && isset($uomsByAbbr[$aliasAbbr])) {
                return [$uomsByAbbr[$aliasAbbr], $qty];
            }
        }

        return [null, null];
    }

    /**
     * Match a UOM key against abbreviation map, name map, and aliases.
     */
    private function matchUom(string $key, $uomsByAbbr, $uomsByName): ?int
    {
        if (isset($uomsByAbbr[$key])) return $uomsByAbbr[$key];
        if (isset($uomsByName[$key])) return $uomsByName[$key];

        $alias = self::UOM_ALIASES[$key] ?? null;
        if ($alias && isset($uomsByAbbr[$alias])) return $uomsByAbbr[$alias];

        return null;
    }

    /**
     * Parse a numeric string, stripping currency symbols, commas, and whitespace.
     */
    private function parseNumber(string $raw, float $default = 0.0): float
    {
        if (! $raw) return $default;

        // Strip currency symbols (RM, $, €, £), commas, spaces
        $cleaned = preg_replace('/[^\d.\-]/', '', str_replace(',', '', $raw));

        return is_numeric($cleaned) ? (float) $cleaned : $default;
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

            // Base UOM (with quantity extraction)
            $baseUomRaw = $getValue('base_uom');
            [$baseUomId, $baseExtractedQty] = $this->resolveUomWithQty($baseUomRaw, $uomsByAbbr, $uomsByName);
            $baseUomNeedsfix = false;
            if (! $baseUomId) {
                $baseUomNeedsfix = true; // user can fix via dropdown (whether empty or unrecognized)
            }

            // Recipe UOM (with quantity extraction, defaults to base UOM)
            $recipeUomRaw = $getValue('recipe_uom');
            $recipeUomId  = null;
            $recipeExtractedQty = null;
            $recipeUomNeedsfix = false;
            if ($recipeUomRaw) {
                [$recipeUomId, $recipeExtractedQty] = $this->resolveUomWithQty($recipeUomRaw, $uomsByAbbr, $uomsByName);
                if (! $recipeUomId) {
                    $recipeUomNeedsfix = true;
                }
            }
            $recipeUomId = $recipeUomId ?? $baseUomId;

            // Category (auto-create if not found)
            $catRaw = $getValue('category');
            $catKey = strtolower($catRaw);
            $catId  = $catKey ? ($catsByName[$catKey] ?? null) : null;
            $catIsNew = false;
            if ($catKey && ! $catId) {
                $catIsNew = true; // will be created on import
            }

            // Numeric fields — use parseNumber to handle currency symbols, commas
            $purchasePrice = $this->parseNumber($getValue('purchase_price'));

            $psRaw = $getValue('pack_size');
            $packSize = $this->parseNumber($psRaw);
            // If pack_size wasn't explicitly provided, use extracted qty from UOM field
            if ($packSize <= 0 && $recipeExtractedQty) {
                $packSize = $recipeExtractedQty;
            } elseif ($packSize <= 0 && $baseExtractedQty) {
                $packSize = $baseExtractedQty;
            }

            $ypRaw = $getValue('yield_percent');
            $yieldPercent = is_numeric($ypRaw) ? min(100, max(0.01, (float) $ypRaw)) : 100.0;

            $isActive = $this->parseBool($getValue('is_active') ?: 'yes');

            // Supplier
            // Supplier — but check if value is actually a prep tag
            $supplierRaw = $getValue('supplier');
            $supplierKey = strtolower(trim($supplierRaw));
            $prepTags = ['prep', 'prep item', 'prep_item', 'prepared', 'in-house', 'in house', 'inhouse', 'kitchen', 'homemade', 'home-made', 'self-made', 'selfmade', 'house made'];
            $supplierIsPrepTag = in_array($supplierKey, $prepTags);

            $supplierId  = null;
            $supplierIsNew = false;
            if ($supplierKey && ! $supplierIsPrepTag) {
                $supplierId = $suppliersByName[$supplierKey] ?? null;
                if (! $supplierId) {
                    $supplierId = $this->fuzzyMatchSupplier($supplierRaw, $suppliersByName);
                    if (! $supplierId) {
                        $supplierIsNew = true;
                    }
                }
            }

            // Type (prep or ingredient)
            // Detect prep items from: explicit type column, supplier column tag, or name keywords
            $nameLower = strtolower($name);
            $nameHasPrep = (bool) preg_match('/\bprep\b|\bprepared\b|\bin-house\b|\bhomemade\b|\bhome-made\b/', $nameLower);

            $hasSupplier = $supplierId || $supplierIsNew;
            $isPrep = false;
            if ($hasTypeColumn) {
                $typeRaw = strtolower($getValue('type'));
                $isPrep = in_array($typeRaw, ['prep', 'prep item', 'prep_item', 'prepared', 'recipe']);
            } elseif ($supplierIsPrepTag || $nameHasPrep) {
                // Supplier column says "PREP" or name contains prep keyword
                $isPrep = true;
            } elseif (! $hasSupplier) {
                // No supplier and no explicit type — collect for AI detection
                if ($name) {
                    $namesForAi[$i] = $name;
                }
            }

            // Clean "PREP" prefix from name if present (e.g. "PREP - Tomato Sauce" → "Tomato Sauce")
            if ($isPrep && $name) {
                $name = preg_replace('/^\s*(prep|prepared)\s*[-–—:]\s*/i', '', $name);
                $name = trim($name);
            }

            // Row needs fix if UOMs are unresolved (not a hard error — user can pick)
            $needsUomFix = $baseUomNeedsfix || $recipeUomNeedsfix;

            $this->rows[] = [
                'row'                    => $rowNum,
                'name'                   => $name,
                'code'                   => $getValue('code') ?: null,
                'category_label'         => $catRaw,
                'ingredient_category_id' => $catId,
                'category_is_new'        => $catIsNew,
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
                'supplier_label'         => $supplierIsPrepTag ? '' : $supplierRaw,
                'supplier_id'            => $supplierId,
                'supplier_is_new'        => $supplierIsNew,
                'is_prep'                => $isPrep,
                'remark'                 => $getValue('remark') ?: null,
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
            . "PREP items are ONLY items that are clearly made in-house by combining other ingredients. Examples: "
            . "house-made sauces, stocks, broths, marinades, dressings, batters, doughs, compound butters, "
            . "house-made spice blends, pastry creams, ganache, house-made simple syrup.\n\n"
            . "INGREDIENT items are everything that can be purchased from a supplier. This includes:\n"
            . "- Raw items: meat, vegetables, fruits, dairy, eggs, flour, sugar, salt\n"
            . "- Store-bought sauces and condiments (soy sauce, ketchup, mayo, oyster sauce, chili sauce, etc.)\n"
            . "- Canned/bottled goods (canned tomatoes, coconut milk, cooking cream, etc.)\n"
            . "- Oils, vinegars, beverages, spices, dried goods\n"
            . "- Packaging materials, cleaning supplies\n"
            . "- ANY item that comes in commercial packaging (bottles, cans, packets, tins, pails, bags)\n\n"
            . "IMPORTANT: Be very conservative. Most items in a supplier list are ingredients, NOT prep items. "
            . "If an item could be either purchased or made in-house, classify it as INGREDIENT.\n\n"
            . "Items to classify:\n{$namesList}\n"
            . "Return a JSON object with a single key \"prep_indices\" containing an array of 1-based item numbers that are PREP items. "
            . "Only include items you are VERY confident are made in-house. When in doubt, always classify as ingredient.\n"
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
        $user = Auth::user();
        if ($user?->company?->ingredients_locked && ! $user->canBypassLock()) {
            session()->flash('error', 'Ingredients are locked. Ask a company admin to unlock in Settings → Company Details.');
            return;
        }
        $companyId = $user->company_id;
        $imported  = 0;
        $skipped   = 0;
        $prepCreated = 0;

        // Cache for newly created suppliers and categories
        $createdSuppliers = [];
        $createdCategories = [];

        foreach ($this->rows as $row) {
            if ($row['skip']) {
                $skipped++;
                continue;
            }

            // Resolve category: use existing ID, or create new
            $catId = $row['ingredient_category_id'];
            if (! $catId && ! empty($row['category_is_new']) && ! empty($row['category_label'])) {
                $catName  = trim($row['category_label']);
                $cacheKey = strtolower($catName);

                if (isset($createdCategories[$cacheKey])) {
                    $catId = $createdCategories[$cacheKey];
                } else {
                    $cat = IngredientCategory::create([
                        'company_id' => $companyId,
                        'name'       => $catName,
                        'parent_id'  => null,
                        'is_active'  => true,
                    ]);
                    $catId = $cat->id;
                    $createdCategories[$cacheKey] = $catId;
                }
            }

            $pp       = $row['purchase_price'];
            $ps       = $row['pack_size'] ?: 1; // treat 0 as 1 for DB storage (user updates later)
            $yp       = $row['yield_percent'];
            $baseCost = $pp / max($ps, 0.0001);
            $cost     = $yp > 0 ? $baseCost / ($yp / 100) : $baseCost;

            if (! empty($row['is_prep'])) {
                // ── Create Prep Item (placeholder Recipe + synced Ingredient) ──
                DB::transaction(function () use ($companyId, $row, $pp, $cost, $catId, &$prepCreated) {
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
                        'ingredient_category_id' => $catId,
                    ]);

                    Ingredient::create([
                        'company_id'             => $companyId,
                        'name'                   => $row['name'],
                        'code'                   => $row['code'],
                        'ingredient_category_id' => $catId,
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
                    'ingredient_category_id' => $catId,
                    'base_uom_id'            => $row['base_uom_id'],
                    'recipe_uom_id'          => $row['recipe_uom_id'],
                    'purchase_price'         => $pp,
                    'pack_size'              => $ps,
                    'yield_percent'          => $yp,
                    'current_cost'           => round($cost, 4),
                    'is_active'              => $row['is_active'],
                    'remark'                 => $row['remark'] ?? null,
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
