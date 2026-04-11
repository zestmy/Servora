<?php

namespace App\Livewire\Ingredients;

use App\Models\AppSetting;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Supplier;
use App\Models\SupplierIngredient;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
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
        'purchase_price'=> ['label' => 'Purchase Price', 'required' => false, 'description' => 'Price per pack'],
        'pack_size'      => ['label' => 'Pack Size',      'required' => false, 'description' => 'Pack size in base UOM'],
        'yield_percent'  => ['label' => 'Yield %',        'required' => false, 'description' => 'Yield percentage (0-100)'],
        'is_active'      => ['label' => 'Active',         'required' => false, 'description' => 'Active status (yes/no)'],
        'supplier'       => ['label' => 'Default Supplier', 'required' => false, 'description' => 'Preferred supplier name'],
    ];

    public array $rows        = [];
    public int   $totalRows   = 0;
    public int   $validRows   = 0;
    public int   $importedCount = 0;
    public int   $skippedCount  = 0;

    protected function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,xlsx|max:10240',
        ];
    }

    protected function messages(): array
    {
        return [
            'file.mimes' => 'Only CSV (.csv) and Excel (.xlsx) files are supported.',
            'file.max'   => 'File size must not exceed 10 MB.',
        ];
    }

    // ── Step 1: Upload & parse headers ──────────────────────────────────────

    public function processUpload(): void
    {
        $this->validate();

        $path = $this->file->getRealPath();
        $ext  = strtolower($this->file->getClientOriginalExtension());

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
            // Exact match found for required fields — use it, skip AI
            $this->columnMapping = $exactMapping;
            $this->aiMapped      = false;
            $this->step          = 'mapping';
        } else {
            // Need AI to help map columns
            $this->step = 'mapping';
            $this->runAiMapping();
        }
    }

    // ── Exact header matching ───────────────────────────────────────────────

    private function tryExactMapping(array $headers): array
    {
        $mapping = [];
        $systemKeys = array_keys(self::SYSTEM_FIELDS);

        // Normalize headers for comparison
        $normalizedMap = [];
        foreach ($headers as $header) {
            $normalized = strtolower(trim(str_replace([' ', '-', '_'], '_', $header)));
            $normalizedMap[$normalized] = $header;
        }

        // Common aliases for each system field
        $aliases = [
            'name'           => ['name', 'ingredient_name', 'ingredient', 'item_name', 'item', 'product_name', 'product', 'description'],
            'code'           => ['code', 'sku', 'item_code', 'ingredient_code', 'product_code', 'internal_code'],
            'category'       => ['category', 'cost_category', 'ingredient_category', 'group', 'type'],
            'base_uom'       => ['base_uom', 'uom', 'unit', 'unit_of_measure', 'purchase_uom', 'purchasing_unit', 'base_unit'],
            'recipe_uom'     => ['recipe_uom', 'recipe_unit', 'cooking_uom', 'cooking_unit'],
            'purchase_price' => ['purchase_price', 'price', 'cost', 'unit_price', 'unit_cost', 'buy_price'],
            'pack_size'      => ['pack_size', 'pack', 'package_size', 'qty_per_pack', 'quantity_per_pack'],
            'yield_percent'  => ['yield_percent', 'yield', 'yield_%', 'yield_pct', 'yield_percentage'],
            'is_active'      => ['is_active', 'active', 'status', 'enabled'],
            'supplier'       => ['supplier', 'default_supplier', 'preferred_supplier', 'supplier_name', 'vendor', 'vendor_name'],
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
            // No API key — fall back to exact mapping only
            $this->columnMapping = $this->tryExactMapping($this->fileHeaders);
            $this->aiMapped      = false;
            $this->aiMapping     = false;
            $this->aiError       = 'OpenRouter API key not configured. Using basic column matching. Go to Settings > API Keys to enable AI mapping.';
            return;
        }

        $model = AppSetting::get('openrouter_model') ?: 'anthropic/claude-sonnet-4-5-20250514';

        // Build sample data for context (first 3 rows)
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

            // Validate that mapped values are actual file headers
            $validMapping = [];
            $headerLower = array_map('strtolower', $this->fileHeaders);

            foreach ($mapped as $sysField => $fileHeader) {
                if (! array_key_exists($sysField, self::SYSTEM_FIELDS)) continue;
                if (! is_string($fileHeader)) continue;

                // Find the exact original header (case-insensitive match)
                $idx = array_search(strtolower($fileHeader), $headerLower);
                if ($idx !== false) {
                    $validMapping[$sysField] = $this->fileHeaders[$idx];
                }
            }

            // Merge with exact mapping (exact takes precedence for any conflicts)
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
        // Validate required fields are mapped
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

        $companyId     = Auth::user()->company_id;
        $suppliersByName = Supplier::where('company_id', $companyId)
            ->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id]);

        $mapping = $this->columnMapping;
        $this->rows = [];

        foreach ($this->fileDataRows as $i => $raw) {
            $rowNum    = $i + 2; // row 1 = header
            $rowErrors = [];

            // Get value by mapped column
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
            if (! $baseUomId) {
                $rowErrors[] = 'Base UOM "' . $baseUomRaw . '" not found';
            }

            // Recipe UOM (defaults to base UOM)
            $recipeUomRaw = $getValue('recipe_uom');
            $recipeUomKey = strtolower($recipeUomRaw);
            $recipeUomId  = null;
            if ($recipeUomKey) {
                $recipeUomId = $uomsByAbbr[$recipeUomKey] ?? $uomsByName[$recipeUomKey] ?? null;
                if (! $recipeUomId) {
                    $rowErrors[] = 'Recipe UOM "' . $recipeUomRaw . '" not found';
                }
            }
            $recipeUomId = $recipeUomId ?? $baseUomId;

            // Category (main category only)
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
                // Try fuzzy match — find closest supplier name
                $supplierId = $this->fuzzyMatchSupplier($supplierRaw, $suppliersByName);
                if (! $supplierId) {
                    $supplierIsNew = true; // Will be created on import
                }
            }

            $this->rows[] = [
                'row'                    => $rowNum,
                'name'                   => $name,
                'code'                   => $getValue('code') ?: null,
                'category_label'         => $catRaw,
                'ingredient_category_id' => $catId,
                'base_uom_label'         => $baseUomRaw,
                'base_uom_id'            => $baseUomId,
                'recipe_uom_label'       => $recipeUomRaw,
                'recipe_uom_id'          => $recipeUomId,
                'purchase_price'         => $purchasePrice,
                'pack_size'              => $packSize,
                'yield_percent'          => $yieldPercent,
                'is_active'              => $isActive,
                'supplier_label'         => $supplierRaw,
                'supplier_id'            => $supplierId,
                'supplier_is_new'        => $supplierIsNew,
                'errors'                 => $rowErrors,
                'skip'                   => ! empty($rowErrors),
            ];
        }

        $this->totalRows = count($this->rows);
        $this->validRows = collect($this->rows)->where('skip', false)->count();
    }

    private function fuzzyMatchSupplier(string $input, $suppliersByName): ?int
    {
        $inputLower = strtolower($input);
        $bestMatch  = null;
        $bestScore  = 0;

        foreach ($suppliersByName as $name => $id) {
            // Check if input contains the supplier name or vice versa
            if (str_contains($inputLower, $name) || str_contains($name, $inputLower)) {
                $score = similar_text($inputLower, $name);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = $id;
                }
            }

            // Also try similar_text with a high threshold
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

        // Cache for newly created suppliers (name → id) to avoid duplicates within same import
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

            // Resolve supplier: use existing ID, or create new supplier
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

            // Create supplier linkage if supplier was resolved
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

            $imported++;
        }

        $this->importedCount = $imported;
        $this->skippedCount  = $skipped;
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
        $this->rows          = [];
        $this->totalRows     = 0;
        $this->validRows     = 0;
        $this->importedCount = 0;
        $this->skippedCount  = 0;
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
        $headers = ['name', 'code', 'category', 'base_uom', 'recipe_uom', 'purchase_price', 'pack_size', 'yield_percent', 'is_active', 'supplier'];
        $sample  = [
            ['Chicken Breast', 'CHK-001', 'Food', 'kg', 'g', '12.50', '1', '80', 'yes', 'ABC Foods Sdn Bhd'],
            ['Apple Crumble', 'ACR-001', 'Food', 'kg', 'gm', '42.69', '1.2', '100', 'yes', 'Fresh Farms Supply'],
            ['Mineral Water', 'WTR-001', 'Beverage', 'bottle', 'bottle', '1.50', '1', '100', 'yes', ''],
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
}
