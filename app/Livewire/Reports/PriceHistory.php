<?php

namespace App\Livewire\Reports;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientPriceHistory;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PriceHistory extends Component
{
    use WithPagination;

    public string $period = 'monthly';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $search = '';
    public string $supplierFilter = '';
    public string $categoryFilter = '';
    public string $sortBy = 'latest';
    public string $perPage = '100'; // '100' | '200' | '300' | '400' | '500' | 'all'
    public string $movementFilter = 'all'; // 'all' | 'increase' | 'decrease' | 'unchanged'

    // Detail view
    public ?int $detailIngredientId = null;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo   = now()->toDateString();
    }

    public function updatedPeriod(): void
    {
        $this->applyPeriod();
        $this->resetPage();
    }

    public function updatedSearch(): void        { $this->resetPage(); }
    public function updatedSupplierFilter(): void { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void      { $this->resetPage(); }
    public function updatedDateTo(): void        { $this->resetPage(); }
    public function updatedSortBy(): void        { $this->resetPage(); }
    public function updatedPerPage(): void       { $this->resetPage(); }
    public function updatedMovementFilter(): void { $this->resetPage(); }

    public function showDetail(int $id): void
    {
        $this->detailIngredientId = $id;
    }

    public function closeDetail(): void
    {
        $this->detailIngredientId = null;
    }

    public function exportPdf()
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to   = Carbon::parse($this->dateTo)->endOfDay();

        $stats   = $this->getStats($from, $to);
        $rows    = $this->buildChangesQuery($from, $to)->get();
        $company = \App\Models\Company::find(\Illuminate\Support\Facades\Auth::user()->company_id);

        $supplier = $this->supplierFilter ? Supplier::find((int) $this->supplierFilter)?->name : null;
        $category = null;
        if ($this->categoryFilter) {
            $category = IngredientCategory::find((int) $this->categoryFilter)?->name;
        }

        $filters = [
            'from'     => $from->format('d M Y'),
            'to'       => $to->format('d M Y'),
            'search'   => $this->search ?: null,
            'supplier' => $supplier,
            'category' => $category,
            'sort'     => $this->sortBy,
            'movement' => $this->movementFilter,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.price-history-report', compact(
            'company', 'stats', 'rows', 'filters'
        ))->setPaper('a4', 'landscape');

        $movementSuffix = $this->movementFilter !== 'all' ? '-' . $this->movementFilter : '';
        $filename = 'price-history-' . $from->format('Ymd') . '-to-' . $to->format('Ymd') . $movementSuffix . '.pdf';

        return response()->streamDownload(fn () => print($pdf->output()), $filename);
    }

    public function render()
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to   = Carbon::parse($this->dateTo)->endOfDay();

        // Stats cards
        $stats = $this->getStats($from, $to);

        // Price changes summary per ingredient. perPage lets the user widen
        // the page to 100/200/…/500, or ALL for full export-ready tables.
        $changesQuery = $this->buildChangesQuery($from, $to);
        if ($this->perPage === 'all') {
            // "All": return everything as a simple collection; the view
            // branches on paginator-vs-collection for the footer.
            $changes = $changesQuery->get();
        } else {
            $size = max(20, (int) $this->perPage);
            $changes = $changesQuery->paginate($size);
        }

        // Detail view data
        $detailData = null;
        if ($this->detailIngredientId) {
            $detailData = $this->getDetailData($this->detailIngredientId, $from, $to);
        }

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $categories = IngredientCategory::roots()->active()->ordered()->with('children')->get();

        return view('livewire.reports.price-history', compact(
            'stats', 'changes', 'suppliers', 'categories', 'detailData'
        ))->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Price History']);
    }

    private function applyPeriod(): void
    {
        $now = now();
        [$this->dateFrom, $this->dateTo] = match ($this->period) {
            'weekly'  => [$now->copy()->startOfWeek()->toDateString(), $now->toDateString()],
            'monthly' => [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()],
            'yearly'  => [$now->copy()->startOfYear()->toDateString(), $now->toDateString()],
            default   => [$this->dateFrom, $this->dateTo],
        };
    }

    private function getStats(Carbon $from, Carbon $to): array
    {
        $records = IngredientPriceHistory::whereBetween('effective_date', [$from, $to]);

        if ($this->supplierFilter) {
            $records->where('supplier_id', $this->supplierFilter);
        }

        $totalRecords = (clone $records)->count();
        $uniqueIngredients = (clone $records)->distinct('ingredient_id')->count('ingredient_id');

        // Change detection: compare latest-in-range against the baseline
        // (last cost BEFORE range, falling back to first in-range when none).
        // havingRaw was previously requiring COUNT>=2 which hid any ingredient
        // whose only in-range record was an import that moved price vs. an
        // outside-range baseline — so new price-watcher imports were invisible.
        $ingredientPrices = IngredientPriceHistory::select('ingredient_id')
            ->selectRaw('MIN(cost) as min_cost, MAX(cost) as max_cost')
            ->selectRaw('(
                COALESCE(
                    (SELECT cost FROM ingredient_price_history iph2
                     WHERE iph2.ingredient_id = ingredient_price_history.ingredient_id
                       AND iph2.effective_date < ?
                     ORDER BY iph2.effective_date DESC, iph2.id DESC
                     LIMIT 1),
                    (SELECT cost FROM ingredient_price_history iph3
                     WHERE iph3.ingredient_id = ingredient_price_history.ingredient_id
                       AND iph3.effective_date BETWEEN ? AND ?
                     ORDER BY iph3.effective_date ASC, iph3.id ASC
                     LIMIT 1)
                )
            ) as first_cost', [$from, $from, $to])
            ->selectRaw('(SELECT cost FROM ingredient_price_history iph4
                WHERE iph4.ingredient_id = ingredient_price_history.ingredient_id
                  AND iph4.effective_date BETWEEN ? AND ?
                ORDER BY iph4.effective_date DESC, iph4.id DESC
                LIMIT 1) as last_cost', [$from, $to])
            ->whereBetween('effective_date', [$from, $to])
            ->groupBy('ingredient_id')
            ->get();

        $increases = 0;
        $decreases = 0;
        $totalChangePct = 0;
        $biggestIncrease = null;
        $biggestDecrease = null;
        $maxIncreasePct = 0;
        $maxDecreasePct = 0;

        foreach ($ingredientPrices as $ip) {
            if ($ip->first_cost > 0) {
                $changePct = (($ip->last_cost - $ip->first_cost) / $ip->first_cost) * 100;
                $totalChangePct += $changePct;

                if ($changePct > 0) {
                    $increases++;
                    if ($changePct > $maxIncreasePct) {
                        $maxIncreasePct = $changePct;
                        $biggestIncrease = $ip->ingredient_id;
                    }
                } elseif ($changePct < 0) {
                    $decreases++;
                    if ($changePct < $maxDecreasePct) {
                        $maxDecreasePct = $changePct;
                        $biggestDecrease = $ip->ingredient_id;
                    }
                }
            }
        }

        $avgChangePct = $ingredientPrices->count() > 0 ? $totalChangePct / $ingredientPrices->count() : 0;

        // Load ingredient names for biggest movers
        $biggestIncreaseName = $biggestIncrease ? Ingredient::find($biggestIncrease)?->name : null;
        $biggestDecreaseName = $biggestDecrease ? Ingredient::find($biggestDecrease)?->name : null;

        return [
            'totalRecords'        => $totalRecords,
            'uniqueIngredients'   => $uniqueIngredients,
            'increases'           => $increases,
            'decreases'           => $decreases,
            'avgChangePct'        => round($avgChangePct, 1),
            'biggestIncreaseName' => $biggestIncreaseName,
            'biggestIncreasePct'  => round($maxIncreasePct, 1),
            'biggestIncreaseId'   => $biggestIncrease,
            'biggestDecreaseName' => $biggestDecreaseName,
            'biggestDecreasePct'  => round($maxDecreasePct, 1),
            'biggestDecreaseId'   => $biggestDecrease,
        ];
    }

    private function buildChangesQuery(Carbon $from, Carbon $to)
    {
        // For each ingredient with activity in the range, compute:
        //   last_cost  — most recent cost in [from, to]
        //   first_cost — baseline price at the START of the range: the last
        //                known cost BEFORE `from`, or the first cost in-range
        //                when no prior record exists. This is what makes
        //                "price increase" detection work when the previous
        //                price was recorded outside the filter window.
        $fromQ = DB::getPdo()->quote($from->toDateString());
        $toQ   = DB::getPdo()->quote($to->toDateString());

        $query = DB::table('ingredient_price_history as iph')
            ->join('ingredients as i', 'i.id', '=', 'iph.ingredient_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'iph.supplier_id')
            ->leftJoin('units_of_measure as u', 'u.id', '=', 'i.base_uom_id')
            ->leftJoin('ingredient_categories as ic', 'ic.id', '=', 'i.ingredient_category_id')
            ->select([
                'i.id as ingredient_id',
                'i.name as ingredient_name',
                'i.code as ingredient_code',
                'u.abbreviation as uom',
                'ic.name as category_name',
                DB::raw('COUNT(iph.id) as record_count'),
                DB::raw('MIN(iph.cost) as min_cost'),
                DB::raw('MAX(iph.cost) as max_cost'),
                DB::raw("(
                    COALESCE(
                        (SELECT iph2.cost FROM ingredient_price_history iph2
                         WHERE iph2.ingredient_id = i.id
                           AND iph2.effective_date < {$fromQ}
                         ORDER BY iph2.effective_date DESC, iph2.id DESC
                         LIMIT 1),
                        (SELECT iph3.cost FROM ingredient_price_history iph3
                         WHERE iph3.ingredient_id = i.id
                           AND iph3.effective_date BETWEEN {$fromQ} AND {$toQ}
                         ORDER BY iph3.effective_date ASC, iph3.id ASC
                         LIMIT 1)
                    )
                ) as first_cost"),
                DB::raw("(SELECT iph4.cost FROM ingredient_price_history iph4
                          WHERE iph4.ingredient_id = i.id
                            AND iph4.effective_date BETWEEN {$fromQ} AND {$toQ}
                          ORDER BY iph4.effective_date DESC, iph4.id DESC
                          LIMIT 1) as last_cost"),
                DB::raw('MAX(iph.effective_date) as latest_date'),
            ])
            ->whereBetween('iph.effective_date', [$from, $to])
            ->groupBy('i.id', 'i.name', 'i.code', 'u.abbreviation', 'ic.name');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('i.name', 'like', '%' . $this->search . '%')
                  ->orWhere('i.code', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->supplierFilter) {
            $query->where('iph.supplier_id', $this->supplierFilter);
        }

        if ($this->categoryFilter) {
            $cat = IngredientCategory::with('children')->find((int) $this->categoryFilter);
            if ($cat) {
                $ids = $cat->children->isNotEmpty()
                    ? $cat->children->pluck('id')->push($cat->id)->toArray()
                    : [$cat->id];
                $query->whereIn('i.ingredient_category_id', $ids);
            }
        }

        // Movement filter — only show ingredients whose first→latest price
        // moved in the requested direction. "unchanged" keeps rows with no
        // price movement (first_cost == last_cost) for completeness.
        if ($this->movementFilter === 'increase') {
            $query->havingRaw('last_cost > first_cost');
        } elseif ($this->movementFilter === 'decrease') {
            $query->havingRaw('last_cost < first_cost');
        } elseif ($this->movementFilter === 'unchanged') {
            $query->havingRaw('last_cost = first_cost');
        }

        $query->orderBy(match ($this->sortBy) {
            'name'   => 'i.name',
            'latest' => 'latest_date',
            'count'  => 'record_count',
            default  => 'latest_date',
        }, match ($this->sortBy) {
            'name' => 'asc',
            default => 'desc',
        });

        return $query;
    }

    private function getDetailData(int $ingredientId, Carbon $from, Carbon $to): ?array
    {
        $ingredient = Ingredient::with(['baseUom', 'recipeUom'])->find($ingredientId);
        if (!$ingredient) return null;

        $records = IngredientPriceHistory::with(['supplier', 'uom'])
            ->where('ingredient_id', $ingredientId)
            ->whereBetween('effective_date', [$from, $to])
            ->orderBy('effective_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // Build change data
        $history = [];
        $prevCost = null;
        foreach ($records as $record) {
            $changePct = null;
            $changeAmt = null;
            if ($prevCost !== null && $prevCost > 0) {
                $changeAmt = $record->cost - $prevCost;
                $changePct = ($changeAmt / $prevCost) * 100;
            }
            $history[] = [
                'date'        => $record->effective_date->format('d M Y'),
                'cost'        => floatval($record->cost),
                'supplier'    => $record->supplier?->name ?? '—',
                'source'      => $record->source ?? '—',
                'change_amt'  => $changeAmt !== null ? round($changeAmt, 4) : null,
                'change_pct'  => $changePct !== null ? round($changePct, 1) : null,
            ];
            $prevCost = floatval($record->cost);
        }

        // Summary
        $costs = $records->pluck('cost')->map(fn ($c) => floatval($c));
        $firstCost = $costs->first();
        $lastCost  = $costs->last();
        $overallChangePct = ($firstCost && $firstCost > 0)
            ? round((($lastCost - $firstCost) / $firstCost) * 100, 1)
            : null;

        return [
            'ingredient'      => $ingredient,
            'history'         => $history,
            'totalRecords'    => $records->count(),
            'minCost'         => $costs->min(),
            'maxCost'         => $costs->max(),
            'avgCost'         => round($costs->avg(), 4),
            'firstCost'       => $firstCost,
            'lastCost'        => $lastCost,
            'overallChangePct' => $overallChangePct,
        ];
    }
}
