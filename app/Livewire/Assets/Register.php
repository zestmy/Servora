<?php

namespace App\Livewire\Assets;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetCount;
use App\Models\Outlet;
use App\Services\AssetOnHandService;
use App\Traits\RemembersListFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What we own, where, and what it is worth — the question the module exists for.
 *
 * Between counts this is the live answer: the last completed count plus every
 * receipt and disposal since, valued at each asset's unit cost. During and
 * after a count it agrees with the count, because both read the same service.
 *
 * TOTALS COVER EVERYTHING, THE TABLE IS PAGED. The rows are assembled in PHP
 * (quantity is derived, not a column, so the database cannot sum it) and only
 * then cut into pages — a total that covered the visible page would be a
 * different number every time somebody clicked next.
 */
class Register extends Component
{
    use WithPagination, ScopesToActiveOutlet, RemembersListFilters;

    public string $search         = '';
    public string $outletFilter   = '';
    public string $categoryFilter = '';

    /** Assets with nothing on hand here are noise until you go looking for them. */
    public bool $includeZero = false;

    public int $perPage = 50;

    protected function rememberedFilters(): array
    {
        return ['outletFilter', 'categoryFilter', 'includeZero'];
    }

    public function mount(): void
    {
        $this->bootRememberedFilters();
    }

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedOutletFilter(): void   { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedIncludeZero(): void    { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'includeZero']);
        $this->resetPage();
    }

    /** The outlets this view is adding up: the one picked, or every one the user can see. */
    private function outletIdsInScope(): array
    {
        $picked = $this->selectedOutletId($this->outletFilter);

        return $picked !== null ? [$picked] : $this->availableOutletIds();
    }

    public function render()
    {
        $this->rememberFilters();

        $outletIds = $this->outletIdsInScope();

        $assets = Asset::with(['category.parent', 'uom'])
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('brand', 'like', $term);
                });
            })
            ->when($this->categoryFilter, function ($q) {
                $cat = AssetCategory::with('children')->find((int) $this->categoryFilter);

                if ($cat) {
                    $q->whereIn('asset_category_id', $cat->children->pluck('id')->push($cat->id)->all());
                }
            })
            ->orderBy('name')
            ->get();

        $quantities = app(AssetOnHandService::class)
            ->forOutlets($outletIds, $assets->pluck('id')->all());

        $rows          = [];
        $totalValue    = 0.0;
        $totalUnits    = 0.0;
        $neverSeen     = 0;
        $byCategory    = [];

        foreach ($assets as $asset) {
            $quantity = 0.0;

            foreach ($outletIds as $outletId) {
                $quantity += $quantities[$outletId][$asset->id] ?? 0.0;
            }

            $quantity = round($quantity, 4);

            if (abs($quantity) < 0.00005) {
                $neverSeen++;

                if (! $this->includeZero) {
                    continue;
                }
            }

            $value = round($quantity * (float) $asset->unit_cost, 4);

            $totalValue += $value;
            $totalUnits += $quantity;

            $categoryName = $asset->category?->name ?? 'Uncategorised';
            $byCategory[$categoryName] = ($byCategory[$categoryName] ?? 0) + $value;

            $rows[] = [
                'id'        => $asset->id,
                'name'      => $asset->name,
                'code'      => $asset->code,
                'brand'     => trim(($asset->brand ?? '') . ' ' . ($asset->model ?? '')),
                'category'  => $categoryName,
                'color'     => $asset->category?->color,
                'uom'       => $asset->uom?->abbreviation ?? $asset->uom?->name ?? '',
                'quantity'  => $quantity,
                'unit_cost' => (float) $asset->unit_cost,
                'value'     => $value,
                'is_active' => (bool) $asset->is_active,
            ];
        }

        // Most valuable first — the register is read to find where the money is,
        // not to look an asset up by name; the search box is for that.
        usort($rows, fn ($a, $b) => $b['value'] <=> $a['value'] ?: strcmp($a['name'], $b['name']));

        arsort($byCategory);

        $page      = $this->getPage();
        $paginated = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $this->perPage, $this->perPage),
            count($rows),
            $this->perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('livewire.assets.register', [
            'rows'          => $paginated,
            'totalValue'    => round($totalValue, 2),
            'totalUnits'    => $totalUnits,
            'lineCount'     => count($rows),
            'neverSeen'     => $neverSeen,
            'byCategory'    => $byCategory,
            'categories'    => AssetCategory::ordered()->get(),
            'outlets'       => $this->filterableOutlets(),
            'scopeLabel'    => $this->scopeLabel($outletIds),
            'lastCount'     => AssetCount::completed()
                ->whereIn('outlet_id', $outletIds ?: [0])
                ->orderByDesc('count_date')->orderByDesc('id')
                ->with('outlet')
                ->first(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Asset Register']);
    }

    /** "Main Outlet" or "All 4 outlets" — the register has to say what it added up. */
    private function scopeLabel(array $outletIds): string
    {
        if (count($outletIds) === 1) {
            return Outlet::whereKey($outletIds[0])->value('name') ?? 'This outlet';
        }

        return 'All ' . count($outletIds) . ' outlets';
    }
}
