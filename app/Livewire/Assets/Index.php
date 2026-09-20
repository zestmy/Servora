<?php

namespace App\Livewire\Assets;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetSupplier;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The Asset List — the Market List for the things an outlet keeps rather than cooks.
 *
 * Same screen shape on purpose: filter strip, a table, one modal that both
 * creates and edits, categories managed from the same page. Somebody who knows
 * the Market List already knows this one.
 *
 * Costs are split out behind `assets.cost` for the same reason they are on
 * ingredients — a name or a category is housekeeping, a price is the value of
 * everything the count then reports.
 */
class Index extends Component
{
    use WithPagination;

    public string $search         = '';
    public string $categoryFilter = '';
    public string $statusFilter   = 'all';
    public string $supplierFilter = '';
    public int    $perPage        = 50;

    /** Bulk selection */
    public array $selectedIds = [];
    public bool  $selectAll   = false;

    /** Asset modal */
    public bool  $showModal = false;
    public ?int  $editingId = null;

    public string $name              = '';
    public string $code              = '';
    public ?int   $asset_category_id = null;
    public ?int   $uom_id            = null;
    public string $unit_cost         = '0';
    public string $brand             = '';
    public string $model             = '';
    public bool   $is_active         = true;
    public string $remark            = '';

    /** Supplier rows inside the modal: [['supplier_id','supplier_sku','last_cost','is_preferred'], …] */
    public array $supplierLinks = [];

    /** Category modal */
    public bool    $showCategoryModal = false;
    public ?int    $editingCategoryId = null;
    public string  $catName           = '';
    public string  $catColor          = '#14b8a6';
    public string  $catSortOrder      = '0';
    public ?int    $catParentId       = null;
    public bool    $catIsActive       = true;

    protected function rules(): array
    {
        return [
            'name'              => 'required|string|max:200',
            'code'              => 'nullable|string|max:60',
            'asset_category_id' => 'nullable|exists:asset_categories,id',
            'uom_id'            => 'required|exists:units_of_measure,id',
            'unit_cost'         => 'required|numeric|min:0',
            'brand'             => 'nullable|string|max:120',
            'model'             => 'nullable|string|max:120',
            'remark'            => 'nullable|string',
            'supplierLinks.*.supplier_id' => 'nullable|exists:suppliers,id',
            'supplierLinks.*.last_cost'   => 'nullable|numeric|min:0',
            'supplierLinks.*.supplier_sku' => 'nullable|string|max:120',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required'   => 'Give the asset a name.',
            'uom_id.required' => 'Choose the unit this asset is bought and counted in.',
        ];
    }

    // ── Abilities ─────────────────────────────────────────────────────────

    public function getCanManageProperty(): bool
    {
        return (bool) Auth::user()?->canDo('assets.manage');
    }

    public function getCanSetCostProperty(): bool
    {
        return (bool) Auth::user()?->canDo('assets.cost');
    }

    public function getCanDeleteProperty(): bool
    {
        return (bool) Auth::user()?->canDo('assets.delete');
    }

    /**
     * Strip the cost fields from a write when the person may not set them.
     *
     * Dropped rather than refused, exactly as the Market List does it: somebody
     * tidying the catalogue should still be able to fix a name without being
     * turned away over a price they never touched.
     */
    private function withoutCostFields(array $data): array
    {
        return $this->canSetCost ? $data : array_diff_key($data, array_flip(['unit_cost']));
    }

    // ── Filters ───────────────────────────────────────────────────────────

    public function updatedSearch(): void         { $this->resetPage(); $this->clearSelection(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); $this->clearSelection(); }
    public function updatedStatusFilter(): void   { $this->resetPage(); $this->clearSelection(); }
    public function updatedSupplierFilter(): void { $this->resetPage(); $this->clearSelection(); }
    public function updatedPerPage(): void        { $this->resetPage(); $this->clearSelection(); }

    public function resetFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'statusFilter', 'supplierFilter']);
        $this->resetPage();
        $this->clearSelection();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selectedIds = $value ? $this->pageAssetIds() : [];
    }

    private function clearSelection(): void
    {
        $this->selectedIds = [];
        $this->selectAll   = false;
    }

    private function pageAssetIds(): array
    {
        return $this->baseQuery()->orderBy('name')
            ->forPage($this->getPage(), $this->perPage)
            ->pluck('id')->map(fn ($id) => (string) $id)->toArray();
    }

    // ── Asset modal ───────────────────────────────────────────────────────

    public function openCreate(): void
    {
        abort_unless($this->canManage, 403);

        $this->resetForm();
        $this->uom_id = UnitOfMeasure::orderBy('name')->value('id');
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $asset = Asset::with('supplierLinks')->findOrFail($id);

        $this->editingId         = $asset->id;
        $this->name              = $asset->name;
        $this->code              = $asset->code ?? '';
        $this->asset_category_id = $asset->asset_category_id;
        $this->uom_id            = $asset->uom_id;
        $this->unit_cost         = (string) floatval($asset->unit_cost);
        $this->brand             = $asset->brand ?? '';
        $this->model             = $asset->model ?? '';
        $this->is_active         = (bool) $asset->is_active;
        $this->remark            = $asset->remark ?? '';

        $this->supplierLinks = $asset->supplierLinks->map(fn ($l) => [
            'supplier_id'  => $l->supplier_id,
            'supplier_sku' => $l->supplier_sku ?? '',
            'last_cost'    => (string) floatval($l->last_cost),
            'is_preferred' => (bool) $l->is_preferred,
        ])->toArray();

        $this->showModal = true;
    }

    public function addSupplierRow(): void
    {
        $this->supplierLinks[] = [
            'supplier_id' => null, 'supplier_sku' => '', 'last_cost' => '0',
            // First supplier on a brand-new asset is the preferred one; there is
            // nothing else it could be, and an asset with suppliers but none
            // preferred silently adds request lines with no vendor on them.
            'is_preferred' => empty($this->supplierLinks),
        ];
    }

    public function removeSupplierRow(int $idx): void
    {
        unset($this->supplierLinks[$idx]);
        $this->supplierLinks = array_values($this->supplierLinks);
    }

    /** Exactly one preferred supplier, always — ticking one unticks the rest. */
    public function setPreferredSupplier(int $idx): void
    {
        foreach ($this->supplierLinks as $i => $link) {
            $this->supplierLinks[$i]['is_preferred'] = ($i === $idx);
        }
    }

    public function save(): void
    {
        // Re-checked here, not just on the route: a Livewire action is its own
        // request, so how the component was first loaded does not authorise it.
        abort_unless($this->canManage, 403);

        $this->validate();

        $data = $this->withoutCostFields([
            'name'              => $this->name,
            'code'              => $this->code ?: null,
            'asset_category_id' => $this->asset_category_id ?: null,
            'uom_id'            => $this->uom_id,
            'unit_cost'         => floatval($this->unit_cost),
            'brand'             => $this->brand ?: null,
            'model'             => $this->model ?: null,
            'is_active'         => $this->is_active,
            'remark'            => $this->remark ?: null,
        ]);

        if ($this->editingId) {
            $asset = Asset::findOrFail($this->editingId);
            $asset->update($data);
        } else {
            $data['company_id'] = Auth::user()->company_id;
            $data['unit_cost'] ??= 0;
            $asset = Asset::create($data);
        }

        $this->saveSupplierLinks($asset);

        session()->flash('success', $this->editingId ? 'Asset updated.' : 'Asset added.');
        $this->closeModal();
    }

    /**
     * Replace the asset's supplier rows with what the modal holds.
     *
     * Rewritten rather than diffed, like the ingredient screen: the rows are a
     * short list edited as a whole, and a delete-then-insert has no order of
     * operations to get wrong. Cost on the link is guarded by `assets.cost`
     * for the same reason the asset's own cost is — it is a price.
     */
    private function saveSupplierLinks(Asset $asset): void
    {
        if (! $this->canSetCost) {
            // Without the cost ability the links can still be added and removed,
            // but never repriced: keep whatever each existing pair already had.
            $existing = AssetSupplier::where('asset_id', $asset->id)
                ->pluck('last_cost', 'supplier_id');
        }

        AssetSupplier::where('asset_id', $asset->id)->delete();

        $seen = [];

        foreach ($this->supplierLinks as $link) {
            $supplierId = (int) ($link['supplier_id'] ?? 0);

            if (! $supplierId || in_array($supplierId, $seen, true)) {
                continue;
            }

            $seen[] = $supplierId;

            AssetSupplier::create([
                'asset_id'     => $asset->id,
                'supplier_id'  => $supplierId,
                'supplier_sku' => $link['supplier_sku'] ?: null,
                'last_cost'    => $this->canSetCost
                    ? floatval($link['last_cost'] ?? 0)
                    : ($existing[$supplierId] ?? null),
                'is_preferred' => (bool) ($link['is_preferred'] ?? false),
            ]);
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'code', 'asset_category_id', 'uom_id',
            'unit_cost', 'brand', 'model', 'remark', 'supplierLinks',
        ]);
        $this->is_active = true;
        $this->unit_cost = '0';
        $this->resetValidation();
    }

    public function toggleActive(int $id): void
    {
        abort_unless($this->canManage, 403);

        $asset = Asset::findOrFail($id);
        $asset->update(['is_active' => ! $asset->is_active]);
    }

    public function delete(int $id): void
    {
        abort_unless($this->canDelete, 403);

        Asset::findOrFail($id)->delete();
        $this->clearSelection();

        session()->flash('success', 'Asset deleted.');
    }

    public function bulkDelete(): void
    {
        abort_unless($this->canDelete, 403);

        $ids = array_map('intval', $this->selectedIds);

        if ($ids === []) {
            return;
        }

        $count = Asset::whereIn('id', $ids)->get()->each->delete()->count();
        $this->clearSelection();

        session()->flash('success', $count . ' asset' . ($count === 1 ? '' : 's') . ' deleted.');
    }

    // ── Categories ────────────────────────────────────────────────────────

    public function openCreateCategory(): void
    {
        abort_unless($this->canManage, 403);

        $this->resetCategoryForm();
        $this->showCategoryModal = true;
    }

    public function openEditCategory(int $id): void
    {
        abort_unless($this->canManage, 403);

        $cat = AssetCategory::findOrFail($id);

        $this->editingCategoryId = $cat->id;
        $this->catName           = $cat->name;
        $this->catColor          = $cat->color;
        $this->catSortOrder      = (string) $cat->sort_order;
        $this->catParentId       = $cat->parent_id;
        $this->catIsActive       = (bool) $cat->is_active;
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        abort_unless($this->canManage, 403);

        $this->validate([
            'catName'      => 'required|string|max:120',
            'catColor'     => 'required|string|max:20',
            'catSortOrder' => 'nullable|numeric',
            'catParentId'  => 'nullable|exists:asset_categories,id',
        ], [
            'catName.required' => 'Give the category a name.',
        ]);

        $data = [
            'name'       => $this->catName,
            'color'      => $this->catColor,
            'sort_order' => (int) $this->catSortOrder,
            // A category cannot be its own parent, which is the only one-step
            // cycle this screen can make (it offers roots only as parents).
            'parent_id'  => ($this->catParentId && $this->catParentId !== $this->editingCategoryId)
                ? $this->catParentId : null,
            'is_active'  => $this->catIsActive,
        ];

        if ($this->editingCategoryId) {
            AssetCategory::findOrFail($this->editingCategoryId)->update($data);
        } else {
            $data['company_id'] = Auth::user()->company_id;
            AssetCategory::create($data);
        }

        session()->flash('success', $this->editingCategoryId ? 'Category updated.' : 'Category added.');
        $this->closeCategoryModal();
    }

    public function deleteCategory(int $id): void
    {
        abort_unless($this->canDelete, 403);

        $cat = AssetCategory::withCount('assets')->findOrFail($id);

        if ($cat->assets_count > 0) {
            session()->flash('error', 'That category still has ' . $cat->assets_count
                . ' asset' . ($cat->assets_count === 1 ? '' : 's') . ' in it. Move them first.');
            return;
        }

        $cat->delete();
        session()->flash('success', 'Category deleted.');
    }

    public function closeCategoryModal(): void
    {
        $this->showCategoryModal = false;
        $this->resetCategoryForm();
    }

    private function resetCategoryForm(): void
    {
        $this->reset(['editingCategoryId', 'catName', 'catParentId']);
        $this->catColor     = '#14b8a6';
        $this->catSortOrder = '0';
        $this->catIsActive  = true;
        $this->resetValidation();
    }

    // ── Query ─────────────────────────────────────────────────────────────

    private function baseQuery()
    {
        $query = Asset::query();

        if ($this->search) {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('code', 'like', $term)
                  ->orWhere('brand', 'like', $term)
                  ->orWhere('model', 'like', $term);
            });
        }

        if ($this->categoryFilter) {
            // A parent stands for its children too, so filtering on "Equipment"
            // does not hide everything filed under "Equipment ▸ Refrigeration".
            $cat = AssetCategory::with('children')->find((int) $this->categoryFilter);

            if ($cat) {
                $ids = $cat->children->pluck('id')->push($cat->id)->all();
                $query->whereIn('asset_category_id', $ids);
            }
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        if ($this->supplierFilter === 'none') {
            $query->whereDoesntHave('suppliers');
        } elseif ($this->supplierFilter) {
            $query->whereHas('suppliers', fn ($q) => $q->where('suppliers.id', (int) $this->supplierFilter));
        }

        return $query;
    }

    public function render()
    {
        $assets = $this->baseQuery()
            ->with(['category.parent', 'uom', 'suppliers'])
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.assets.index', [
            'assets'     => $assets,
            'categories' => AssetCategory::withCount('assets')->ordered()->get(),
            'rootCategories' => AssetCategory::roots()->ordered()->get(),
            'uoms'       => UnitOfMeasure::orderBy('name')->get(),
            'suppliers'  => Supplier::selectable(
                collect($this->supplierLinks)->pluck('supplier_id')->all()
            )->orderBy('name')->get(),
            'catalogueValue' => (clone $assets)->getCollection()
                ->sum(fn ($a) => (float) $a->unit_cost),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Asset List']);
    }
}
