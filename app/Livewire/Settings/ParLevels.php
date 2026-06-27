<?php

namespace App\Livewire\Settings;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\IngredientParLevel;
use App\Models\Outlet;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ParLevels extends Component
{
    use WithPagination;

    public ?int $outletId = null;
    public string $search = '';
    public string $categoryFilter = '';
    public string $statusFilter = '';   // '', 'set', 'unset'
    public array $parLevels = [];        // [ingredient_id => par_level_value]

    // Bulk tools
    public string $bulkValue = '';
    public $copyFromOutletId = null;

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

        foreach ($ids as $id) {
            $this->persistOne((int) $id, $value);
        }

        $this->bulkValue = '';
        session()->flash('success', $ids->count() . ' par level(s) updated for the current filter.');
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

        // Summary: how many active ingredients have a par level set for this outlet.
        $totalIngredients = Ingredient::where('is_active', true)->count();
        $setCount = $this->outletId
            ? IngredientParLevel::where('outlet_id', $this->outletId)->where('par_level', '>', 0)->count()
            : 0;

        return view('livewire.settings.par-levels', compact(
            'outlets', 'categories', 'ingredients', 'totalIngredients', 'setCount'
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

    private function persistOne(int $ingredientId, $value): void
    {
        if (! $this->outletId) {
            return;
        }

        $companyId = Auth::user()->company_id;
        $parLevel  = floatval($value);

        if ($parLevel > 0) {
            IngredientParLevel::updateOrCreate(
                ['ingredient_id' => $ingredientId, 'outlet_id' => $this->outletId],
                ['par_level' => $parLevel, 'company_id' => $companyId]
            );
            $this->parLevels[$ingredientId] = (string) $parLevel;
        } else {
            IngredientParLevel::where('ingredient_id', $ingredientId)
                ->where('outlet_id', $this->outletId)
                ->delete();
            unset($this->parLevels[$ingredientId]);
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
}
