<?php

namespace App\Livewire\Assets;

use App\Models\AssetCount;
use App\Models\AssetMovement;
use App\Models\Supplier;
use App\Traits\HasQuickDateRanges;
use App\Traits\RemembersListFilters;
use App\Traits\ScopesToActiveOutlet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every asset document in one list: counts, receipts, disposals.
 *
 * Three tabs over two models, described as data for the same reason
 * Inventory\Index does it — three near-identical blocks of query building are
 * three chances for a filter to work on one tab and quietly do nothing on
 * another.
 */
class Records extends Component
{
    use WithPagination, ScopesToActiveOutlet, RemembersListFilters, HasQuickDateRanges;

    private const TABS = [
        'counts' => [
            'label'    => 'Counts',
            'model'    => AssetCount::class,
            'date'     => 'count_date',
            'amount'   => 'total_asset_value',
            'money'    => 'Counted value',
            'status'   => true,
            'supplier' => false,
            'type'     => null,
        ],
        'receipts' => [
            'label'    => 'Receipts',
            'model'    => AssetMovement::class,
            'date'     => 'movement_date',
            'amount'   => 'total_cost',
            'money'    => 'Received value',
            'status'   => false,
            'supplier' => true,
            'type'     => AssetMovement::TYPE_RECEIPT,
        ],
        'disposals' => [
            'label'    => 'Disposals',
            'model'    => AssetMovement::class,
            'date'     => 'movement_date',
            'amount'   => 'total_cost',
            'money'    => 'Value written off',
            'status'   => false,
            'supplier' => false,
            'type'     => AssetMovement::TYPE_DISPOSAL,
        ],
    ];

    public string $tab = 'counts';

    public string $search         = '';
    public string $outletFilter   = '';
    public string $statusFilter   = '';
    public string $supplierFilter = '';
    public string $dateFrom       = '';
    public string $dateTo         = '';
    public int    $perPage        = 25;

    protected function rememberedFilters(): array
    {
        return ['outletFilter', 'statusFilter', 'supplierFilter', 'quickRange', 'dateFrom', 'dateTo'];
    }

    /** A supplier filter means nothing on Counts — keep each tab's bundle apart. */
    protected function rememberedFilterScope(): string
    {
        return $this->tab;
    }

    public function mount(): void
    {
        $tab = request('tab');

        if (isset(self::TABS[$tab])) {
            $this->tab = $tab;
        }

        $this->bootRememberedFilters();
        $this->bootQuickRange();
    }

    public function updatedTab(): void
    {
        $this->reset(['search', 'statusFilter', 'supplierFilter']);
        $this->bootRememberedFilters();
        $this->resetPage();
    }

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedOutletFilter(): void   { $this->resetPage(); }
    public function updatedStatusFilter(): void   { $this->resetPage(); }
    public function updatedSupplierFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void       { $this->quickRange = ''; $this->resetPage(); }
    public function updatedDateTo(): void         { $this->quickRange = ''; $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'supplierFilter', 'outletFilter']);
        $this->setQuickRange($this->defaultQuickRange());
    }

    private function tabConfig(?string $tab = null): array
    {
        return self::TABS[$tab ?? $this->tab] ?? self::TABS['counts'];
    }

    private function filtered(): Builder
    {
        $config = $this->tabConfig();
        $query  = $config['model']::query();

        if ($config['type'] !== null) {
            $query->where('movement_type', $config['type']);
        }

        $this->scopeByOutletFilter($query, $this->outletFilter);

        if ($this->dateFrom !== '') {
            $query->whereDate($config['date'], '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate($config['date'], '<=', $this->dateTo);
        }

        if ($config['status'] && $this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($config['supplier'] && $this->supplierFilter !== '') {
            $query->where('supplier_id', (int) $this->supplierFilter);
        }

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('reference_number', 'like', $term)
                  ->orWhere('notes', 'like', $term);
            });
        }

        return $query;
    }

    // ── Deletes ───────────────────────────────────────────────────────────

    public function deleteCount(int $id): void
    {
        abort_unless(Auth::user()?->canDo('assets.counts.delete'), 403);

        $count = AssetCount::findOrFail($id);
        abort_unless(Auth::user()->canAccessOutlet($count->outlet_id), 403);

        $count->delete();

        session()->flash('success', 'Count deleted. The register falls back to the count before it.');
    }

    public function deleteMovement(int $id): void
    {
        abort_unless(Auth::user()?->canDo('assets.movements.delete'), 403);

        $movement = AssetMovement::findOrFail($id);
        abort_unless(Auth::user()->canAccessOutlet($movement->outlet_id), 403);

        $label = $movement->typeLabel();
        $movement->delete();

        session()->flash('success', $label . ' deleted. The register no longer counts it.');
    }

    public function render()
    {
        $this->rememberFilters();

        $config = $this->tabConfig();

        $records = $this->filtered()
            ->with($this->tab === 'counts'
                ? ['outlet', 'department', 'createdBy']
                : ['outlet', 'supplier', 'department', 'createdBy'])
            ->withCount('lines')
            ->orderByDesc($config['date'])
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('livewire.assets.records', [
            'records'    => $records,
            'tabs'       => array_map(fn ($t) => $t['label'], self::TABS),
            'config'     => $config,
            'outlets'    => $this->filterableOutlets(),
            'suppliers'  => Supplier::selectable($this->supplierFilter ?: [])->orderBy('name')->get(),
            'rangeTotal' => round((float) $this->filtered()->sum($config['amount']), 2),
            'rangeLabel' => $this->rangeLabel(),
            'quickRangeOptions' => static::quickRangeOptions(),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Asset Records']);
    }
}
