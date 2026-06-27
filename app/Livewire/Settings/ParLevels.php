<?php

namespace App\Livewire\Settings;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientParLevel;
use App\Models\Outlet;
use App\Services\CsvExportService;
use App\Services\StockOnHandService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class ParLevels extends Component
{
    use WithPagination, WithFileUploads;

    public ?int $outletId = null;
    public string $search = '';
    public string $categoryFilter = '';
    public string $statusFilter = '';   // '', 'set', 'unset'
    public array $parLevels = [];        // [ingredient_id => par_level_value]

    // Bulk tools
    public string $bulkValue = '';
    public bool $bulkAllOutlets = false;
    public $copyFromOutletId = null;

    // CSV / XLSX import
    public $importFile = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->outletId = $user->activeOutletId()
            ?? Outlet::where('company_id', $user->company_id)->value('id');
        $this->loadParLevels();
    }

    public function updatedOutletId(): void
    {
        $this->resetPage();
        $this->loadParLevels();
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    /** Quick edit: persist a single ingredient's par level the moment its field changes. */
    public function updatedParLevels($value, $key): void
    {
        $this->persistOne((int) $key, $value);
        $this->dispatch('par-saved', id: (int) $key);
    }

    /** Explicit bulk save of everything currently held in state. */
    public function saveAll(): void
    {
        foreach ($this->parLevels as $ingredientId => $value) {
            $this->persistOne((int) $ingredientId, $value);
        }
        session()->flash('success', 'Par levels saved.');
    }

    /** Apply one value to every ingredient currently matched by the filters. */
    public function applyToFiltered(): void
    {
        if ($this->bulkValue === '' || ! $this->outletId) {
            return;
        }

        $value = floatval($this->bulkValue);
        $ids   = $this->filteredIngredientQuery()->pluck('id');

        $outletIds = $this->bulkAllOutlets
            ? Outlet::where('company_id', Auth::user()->company_id)->pluck('id')->all()
            : [$this->outletId];

        foreach ($outletIds as $oid) {
            foreach ($ids as $id) {
                $this->persistOne((int) $id, $value, (int) $oid);
            }
        }

        $this->bulkValue = '';
        $scope = $this->bulkAllOutlets ? count($outletIds) . ' outlet(s)' : 'this outlet';
        session()->flash('success', $ids->count() . " par level(s) updated for {$scope}.");
        $this->loadParLevels();
    }

    /** Fill only the currently-blank par levels (for the filtered set) with a usage-based suggestion. */
    public function fillBlanksWithSuggested(): void
    {
        if (! $this->outletId) {
            return;
        }

        $ids = $this->filteredIngredientQuery()->pluck('id')->map(fn ($i) => (int) $i)->all();
        $suggested = StockOnHandService::monthlyPurchaseAverage($ids, $this->outletId, 3);

        $existing = IngredientParLevel::where('outlet_id', $this->outletId)
            ->where('par_level', '>', 0)
            ->pluck('ingredient_id')
            ->all();
        $existing = array_flip($existing);

        $filled = 0;
        foreach ($ids as $id) {
            if (isset($existing[$id])) continue;          // keep already-set values
            $value = $suggested[$id] ?? 0;
            if ($value > 0) {
                $this->persistOne($id, $value);
                $filled++;
            }
        }

        session()->flash('success', "{$filled} blank par level(s) filled from recent usage.");
    }

    /** Apply the usage-based suggestion to a single ingredient. */
    public function applySuggested(int $ingredientId): void
    {
        if (! $this->outletId) {
            return;
        }

        $suggested = StockOnHandService::monthlyPurchaseAverage([$ingredientId], $this->outletId, 3);
        $value = $suggested[$ingredientId] ?? 0;

        if ($value > 0) {
            $this->persistOne($ingredientId, $value);
            $this->dispatch('par-saved', id: $ingredientId);
        }
    }

    /** Copy all par levels from another outlet into the current one. */
    public function copyFromOutlet(): void
    {
        $sourceOutletId = (int) $this->copyFromOutletId;

        if (! $sourceOutletId || ! $this->outletId || $sourceOutletId === $this->outletId) {
            return;
        }

        $companyId = Auth::user()->company_id;
        $source = IngredientParLevel::where('outlet_id', $sourceOutletId)->get();

        foreach ($source as $row) {
            IngredientParLevel::updateOrCreate(
                ['ingredient_id' => $row->ingredient_id, 'outlet_id' => $this->outletId],
                ['par_level' => $row->par_level, 'company_id' => $companyId]
            );
        }

        $this->copyFromOutletId = null;
        $this->loadParLevels();
        session()->flash('success', $source->count() . ' par level(s) copied from the selected outlet.');
    }

    /** Download every active ingredient with its current par level for this outlet (doubles as an import template). */
    public function exportCsv()
    {
        $rows = Ingredient::with('baseUom')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($ing) => [
                $ing->name,
                $ing->code ?? '',
                $ing->baseUom?->abbreviation ?? '',
                $this->parLevels[$ing->id] ?? '',
            ]);

        $outletName = Outlet::find($this->outletId)?->name ?? 'outlet';
        $slug = \Illuminate\Support\Str::slug($outletName);

        return CsvExportService::download(
            "par-levels-{$slug}-" . now()->format('Y-m-d') . '.csv',
            ['Ingredient', 'Code', 'Base UOM', 'Par Level'],
            $rows->toArray()
        );
    }

    /** Import par levels from an uploaded CSV/XLSX, matching ingredients by code then name. */
    public function importParLevels(): void
    {
        $this->validate(['importFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120']);

        if (! $this->outletId) {
            session()->flash('error', 'Select an outlet before importing.');
            return;
        }

        $path = $this->importFile->getRealPath();
        $ext  = strtolower($this->importFile->getClientOriginalExtension());
        $parsed = in_array($ext, ['xlsx', 'xls']) ? $this->parseSpreadsheet($path, true) : $this->parseSpreadsheet($path, false);

        if (empty($parsed)) {
            session()->flash('error', 'No rows found in the file.');
            $this->reset('importFile');
            return;
        }

        // Build lookup of active ingredients by code and by name.
        $byCode = [];
        $byName = [];
        foreach (Ingredient::where('is_active', true)->get(['id', 'name', 'code']) as $ing) {
            if ($ing->code) $byCode[mb_strtolower(trim($ing->code))] = $ing->id;
            $byName[mb_strtolower(trim($ing->name))] = $ing->id;
        }

        $updated = 0;
        $cleared = 0;
        $unmatched = 0;

        foreach ($parsed as $row) {
            $code = mb_strtolower(trim((string) $this->cell($row, ['code', 'ingredient code'])));
            $name = mb_strtolower(trim((string) $this->cell($row, ['ingredient', 'ingredient name', 'name'])));
            $parRaw = $this->cell($row, ['par level', 'par', 'par_level']);

            $ingredientId = ($code !== '' && isset($byCode[$code])) ? $byCode[$code]
                : (($name !== '' && isset($byName[$name])) ? $byName[$name] : null);

            if (! $ingredientId) {
                $unmatched++;
                continue;
            }

            if (trim((string) $parRaw) === '') {
                continue; // blank cell = leave unchanged
            }

            $value = floatval($parRaw);
            $this->persistOne((int) $ingredientId, $value);
            $value > 0 ? $updated++ : $cleared++;
        }

        $this->reset('importFile');
        $this->loadParLevels();
        session()->flash('success', "Import complete: {$updated} set, {$cleared} cleared, {$unmatched} unmatched.");
    }

    public function render()
    {
        $user = Auth::user();

        $outlets = Outlet::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = IngredientCategory::with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->whereNull('parent_id')
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $ingredients = $this->filteredIngredientQuery()->paginate(50);

        // Context figures for the visible page only (keeps it cheap).
        $pageIds   = $ingredients->getCollection()->pluck('id')->all();
        $onHand    = StockOnHandService::currentForOutlet($pageIds, (int) $this->outletId);
        $suggested = StockOnHandService::monthlyPurchaseAverage($pageIds, (int) $this->outletId, 3);

        // Summary: how many active ingredients have a par level set for this outlet.
        $totalIngredients = Ingredient::where('is_active', true)->count();
        $setCount = $this->outletId
            ? IngredientParLevel::where('outlet_id', $this->outletId)->where('par_level', '>', 0)->count()
            : 0;

        return view('livewire.settings.par-levels', compact(
            'outlets', 'categories', 'ingredients', 'onHand', 'suggested', 'totalIngredients', 'setCount'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Par Levels']);
    }

    private function filteredIngredientQuery()
    {
        $query = Ingredient::with('baseUom', 'ingredientCategory')
            ->where('is_active', true)
            ->orderBy('name');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter) {
            // Selected category plus its sub-categories (kept inside one closure so
            // the company global scope still bounds the whole condition).
            $catIds = IngredientCategory::where(function ($q) {
                $q->where('id', $this->categoryFilter)
                  ->orWhere('parent_id', $this->categoryFilter);
            })->pluck('id');

            $query->whereIn('ingredient_category_id', $catIds);
        }

        if ($this->statusFilter && $this->outletId) {
            $setIds = IngredientParLevel::where('outlet_id', $this->outletId)
                ->where('par_level', '>', 0)
                ->pluck('ingredient_id');

            $this->statusFilter === 'set'
                ? $query->whereIn('id', $setIds)
                : $query->whereNotIn('id', $setIds);
        }

        return $query;
    }

    private function persistOne(int $ingredientId, $value, ?int $outletId = null): void
    {
        $outletId ??= $this->outletId;
        if (! $outletId) {
            return;
        }

        $companyId = Auth::user()->company_id;
        $parLevel  = floatval($value);

        if ($parLevel > 0) {
            IngredientParLevel::updateOrCreate(
                ['ingredient_id' => $ingredientId, 'outlet_id' => $outletId],
                ['par_level' => $parLevel, 'company_id' => $companyId]
            );
            if ($outletId === $this->outletId) {
                $this->parLevels[$ingredientId] = (string) $parLevel;
            }
        } else {
            IngredientParLevel::where('ingredient_id', $ingredientId)
                ->where('outlet_id', $outletId)
                ->delete();
            if ($outletId === $this->outletId) {
                unset($this->parLevels[$ingredientId]);
            }
        }
    }

    private function loadParLevels(): void
    {
        if (! $this->outletId) {
            $this->parLevels = [];
            return;
        }

        $this->parLevels = IngredientParLevel::where('outlet_id', $this->outletId)
            ->pluck('par_level', 'ingredient_id')
            ->map(fn ($v) => (string) floatval($v))
            ->toArray();
    }

    /** Parse an uploaded CSV or XLSX into an array of header-keyed rows. */
    private function parseSpreadsheet(string $path, bool $isXlsx): array
    {
        if ($isXlsx) {
            $reader = new XlsxReader();
        } else {
            $options = new CsvOptions();
            $options->FIELD_ENCLOSURE = '"';
            $reader = new CsvReader($options);
        }

        $reader->open($path);

        $rows    = [];
        $headers = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn ($c) => trim((string) $c->getValue()), $row->getCells());

                if ($headers === null) {
                    if (! empty($cells[0])) {
                        $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]);
                    }
                    $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $cells);
                    continue;
                }

                if (count(array_filter($cells, fn ($c) => $c !== '')) === 0) continue;

                $padded = array_slice(array_pad($cells, count($headers), ''), 0, count($headers));
                $rows[] = array_combine($headers, $padded);
            }
            break;
        }

        $reader->close();
        return $rows;
    }

    /** Fetch a cell from a header-keyed row by any of the candidate header names. */
    private function cell(array $row, array $candidates): string
    {
        foreach ($candidates as $key) {
            if (array_key_exists($key, $row)) {
                return (string) $row[$key];
            }
        }
        return '';
    }
}
