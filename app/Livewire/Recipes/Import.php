<?php

namespace App\Livewire\Recipes;

use App\Models\IngredientCategory;
use App\Models\Recipe;
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

    public array $rows         = [];
    public int   $totalRows    = 0;
    public int   $validRows    = 0;
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

        $firstRow = $rawRows[0];
        if (! array_key_exists('name', $firstRow)) {
            $this->addError('file', 'Missing required column "name". Check your file headers.');
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
            $rowNum    = $i + 2;
            $rowErrors = [];

            // Name
            $name = trim($raw['name'] ?? '');
            if (! $name) {
                $rowErrors[] = 'Name is required';
            }

            // Yield UOM
            $yieldUomKey = strtolower(trim($raw['yield_uom'] ?? ''));
            $yieldUomId  = $yieldUomKey
                ? ($uomsByAbbr[$yieldUomKey] ?? $uomsByName[$yieldUomKey] ?? null)
                : null;
            if ($yieldUomKey && ! $yieldUomId) {
                $rowErrors[] = 'Yield UOM "' . $raw['yield_uom'] . '" not found';
            }

            // Cost Center
            $catKey = strtolower(trim($raw['cost_center'] ?? ''));
            $catId  = $catKey ? ($catsByName[$catKey] ?? null) : null;
            if ($catKey && ! $catId) {
                $rowErrors[] = 'Cost Center "' . $raw['cost_center'] . '" not found';
            }

            // Numerics
            $yieldQty = is_numeric($raw['yield_quantity'] ?? null)
                ? max(0.0001, (float) $raw['yield_quantity'])
                : 1.0;

            $sellingPrice = is_numeric($raw['selling_price'] ?? null)
                ? max(0, (float) $raw['selling_price'])
                : 0.0;

            $isActive = $this->parseBool($raw['is_active'] ?? 'yes');

            $this->rows[] = [
                'row'                    => $rowNum,
                'name'                   => $name,
                'code'                   => trim($raw['code'] ?? '') ?: null,
                'description'            => trim($raw['description'] ?? '') ?: null,
                'yield_quantity'         => $yieldQty,
                'yield_uom_label'        => $raw['yield_uom'] ?? '',
                'yield_uom_id'           => $yieldUomId,
                'selling_price'          => $sellingPrice,
                'cost_center_label'      => $raw['cost_center'] ?? '',
                'ingredient_category_id' => $catId,
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

            Recipe::create([
                'company_id'             => $companyId,
                'name'                   => $row['name'],
                'code'                   => $row['code'],
                'description'            => $row['description'],
                'yield_quantity'         => $row['yield_quantity'],
                'yield_uom_id'           => $row['yield_uom_id'],
                'selling_price'          => $row['selling_price'],
                'cost_per_yield_unit'    => 0,
                'ingredient_category_id' => $row['ingredient_category_id'],
                'is_active'              => $row['is_active'],
                'is_prep'                => false,
            ]);

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
        $this->rows          = [];
        $this->totalRows     = 0;
        $this->validRows     = 0;
        $this->importedCount = 0;
        $this->skippedCount  = 0;
        $this->resetValidation();
    }

    public function downloadTemplate()
    {
        $headers = ['name', 'code', 'description', 'yield_quantity', 'yield_uom', 'selling_price', 'cost_center', 'is_active'];
        $sample  = [
            ['Nasi Lemak', 'NL-001', 'Classic coconut rice set', '1', 'portion', '12.90', 'Food', 'yes'],
            ['Teh Tarik', 'TT-001', 'Pulled milk tea', '1', 'cup', '3.50', 'Beverage', 'yes'],
            ['Chocolate Cake Slice', 'CK-001', '', '1', 'slice', '8.00', 'Food', 'yes'],
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
            'recipe_import_template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function render()
    {
        return view('livewire.recipes.import')
            ->layout('layouts.app', ['title' => 'Import Recipes']);
    }

    // ── Parsers ───────────────────────────────────────────────────────────────

    private function parseCsv(string $path): array
    {
        $reader  = new CsvReader();
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
            break;
        }

        $reader->close();
        return $rows;
    }

    private function parseXlsx(string $path): array
    {
        $reader  = new XlsxReader();
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
            break;
        }

        $reader->close();
        return $rows;
    }

    private function parseBool(string $val): bool
    {
        return in_array(strtolower(trim($val)), ['yes', '1', 'true', 'active', 'y']);
    }
}
