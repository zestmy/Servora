<?php

namespace App\Livewire\Ingredients;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class Import extends Component
{
    use WithFileUploads;

    public $file = null;

    public string $step = 'upload'; // upload | preview | done

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

    public function processUpload(): void
    {
        $this->validate();

        $path = $this->file->getRealPath();
        $ext  = strtolower($this->file->getClientOriginalExtension());

        try {
            $rawRows = ($ext === 'xlsx') ? $this->parseXlsx($path) : $this->parseCsv($path);
        } catch (\Throwable $e) {
            $this->addError('file', 'Could not parse file: ' . $e->getMessage());
            return;
        }

        if (empty($rawRows)) {
            $this->addError('file', 'The file appears to be empty or has no data rows after the header.');
            return;
        }

        // Validate required headers exist
        $firstRow = $rawRows[0];
        if (! array_key_exists('name', $firstRow) || ! array_key_exists('base_uom', $firstRow)) {
            $this->addError('file', 'Missing required columns. The file must include at least "name" and "base_uom" columns.');
            return;
        }

        // Load lookup tables once
        $uomsByAbbr = UnitOfMeasure::all()->mapWithKeys(fn ($u) => [strtolower($u->abbreviation) => $u->id]);
        $uomsByName = UnitOfMeasure::all()->mapWithKeys(fn ($u) => [strtolower($u->name) => $u->id]);
        $catsByName = IngredientCategory::whereNull('parent_id')
            ->get()
            ->mapWithKeys(fn ($c) => [strtolower($c->name) => $c->id]);

        $this->rows = [];

        foreach ($rawRows as $i => $raw) {
            $rowNum    = $i + 2; // row 1 = header, data starts at 2
            $rowErrors = [];

            // Name
            $name = trim($raw['name'] ?? '');
            if (! $name) {
                $rowErrors[] = 'Name is required';
            }

            // Base UOM
            $baseUomKey = strtolower(trim($raw['base_uom'] ?? ''));
            $baseUomId  = $uomsByAbbr[$baseUomKey] ?? $uomsByName[$baseUomKey] ?? null;
            if (! $baseUomId) {
                $rowErrors[] = 'Base UOM "' . ($raw['base_uom'] ?? '') . '" not found';
            }

            // Recipe UOM (defaults to base UOM)
            $recipeUomKey = strtolower(trim($raw['recipe_uom'] ?? ''));
            $recipeUomId  = null;
            if ($recipeUomKey) {
                $recipeUomId = $uomsByAbbr[$recipeUomKey] ?? $uomsByName[$recipeUomKey] ?? null;
                if (! $recipeUomId) {
                    $rowErrors[] = 'Recipe UOM "' . $raw['recipe_uom'] . '" not found';
                }
            }
            $recipeUomId = $recipeUomId ?? $baseUomId;

            // Cost Center (main category only)
            $catKey = strtolower(trim($raw['cost_center'] ?? ''));
            $catId  = $catKey ? ($catsByName[$catKey] ?? null) : null;
            if ($catKey && ! $catId) {
                $rowErrors[] = 'Cost Center "' . $raw['cost_center'] . '" not found';
            }

            // Numeric fields
            $purchasePrice = is_numeric($raw['purchase_price'] ?? null)
                ? max(0, (float) $raw['purchase_price'])
                : 0.0;

            $yieldPercent = is_numeric($raw['yield_percent'] ?? null)
                ? min(100, max(0.01, (float) $raw['yield_percent']))
                : 100.0;

            $isActive = $this->parseBool($raw['is_active'] ?? 'yes');

            $this->rows[] = [
                'row'                    => $rowNum,
                'name'                   => $name,
                'code'                   => trim($raw['code'] ?? '') ?: null,
                'cost_center_label'      => $raw['cost_center'] ?? '',
                'ingredient_category_id' => $catId,
                'base_uom_label'         => $raw['base_uom'] ?? '',
                'base_uom_id'            => $baseUomId,
                'recipe_uom_label'       => $raw['recipe_uom'] ?? '',
                'recipe_uom_id'          => $recipeUomId,
                'purchase_price'         => $purchasePrice,
                'yield_percent'          => $yieldPercent,
                'is_active'              => $isActive,
                'errors'                 => $rowErrors,
                'skip'                   => ! empty($rowErrors),
            ];
        }

        $this->totalRows = count($this->rows);
        $this->validRows = collect($this->rows)->where('skip', false)->count();
        $this->step      = 'preview';
    }

    public function import(): void
    {
        $companyId = Auth::user()->company_id;
        $imported  = 0;
        $skipped   = 0;

        foreach ($this->rows as $row) {
            if ($row['skip']) {
                $skipped++;
                continue;
            }

            $pp   = $row['purchase_price'];
            $yp   = $row['yield_percent'];
            $cost = $yp > 0 ? $pp / ($yp / 100) : $pp;

            Ingredient::create([
                'company_id'             => $companyId,
                'name'                   => $row['name'],
                'code'                   => $row['code'],
                'ingredient_category_id' => $row['ingredient_category_id'],
                'base_uom_id'            => $row['base_uom_id'],
                'recipe_uom_id'          => $row['recipe_uom_id'],
                'purchase_price'         => $pp,
                'yield_percent'          => $yp,
                'current_cost'           => $cost,
                'is_active'              => $row['is_active'],
            ]);

            $imported++;
        }

        $this->importedCount = $imported;
        $this->skippedCount  = $skipped;
        $this->step          = 'done';
    }

    public function restart(): void
    {
        $this->file         = null;
        $this->step         = 'upload';
        $this->rows         = [];
        $this->totalRows    = 0;
        $this->validRows    = 0;
        $this->importedCount = 0;
        $this->skippedCount  = 0;
        $this->resetValidation();
    }

    public function downloadTemplate()
    {
        $headers = ['name', 'code', 'cost_center', 'base_uom', 'recipe_uom', 'purchase_price', 'yield_percent', 'is_active'];
        $sample  = [
            ['Chicken Breast', 'CHK-001', 'Food', 'kg', 'g', '12.50', '80', 'yes'],
            ['Mineral Water', 'WTR-001', 'Beverage', 'bottle', 'bottle', '1.50', '100', 'yes'],
            ['All-Purpose Flour', 'FLR-001', 'Food', 'kg', 'g', '3.20', '100', 'yes'],
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
            ->layout('layouts.app', ['title' => 'Import Ingredients']);
    }

    // ── Parsers ───────────────────────────────────────────────────────────────

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
                    $headers = array_map('strtolower', array_map('trim', $cells));
                    continue;
                }

                if (array_filter($cells) === []) continue; // skip blank rows

                $rows[] = array_combine(
                    $headers,
                    array_pad($cells, count($headers), '')
                );
            }
            break; // first sheet only
        }

        $reader->close();
        return $rows;
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
                    $headers = array_map('strtolower', array_map('trim', $cells));
                    continue;
                }

                if (array_filter($cells) === []) continue;

                $rows[] = array_combine(
                    $headers,
                    array_pad($cells, count($headers), '')
                );
            }
            break; // first sheet only
        }

        $reader->close();
        return $rows;
    }

    private function parseBool(string $val): bool
    {
        return in_array(strtolower(trim($val)), ['yes', '1', 'true', 'active', 'y']);
    }
}
