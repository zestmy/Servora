<?php

namespace App\Livewire\Kitchen;

use App\Models\CentralKitchen;
use App\Models\ProductionRecipe;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ProductionRecipes extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $kitchenFilter = null;
    public string $statusFilter = '';

    protected $queryString = [
        'search'        => ['except' => ''],
        'kitchenFilter' => ['except' => null, 'as' => 'kitchen'],
        'statusFilter'  => ['except' => '', 'as' => 'status'],
    ];

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedKitchenFilter(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void  { $this->resetPage(); }

    // ── Actions ─────────────────────────────────────────────────────────

    /*
     * The kitchen routes carry only `kitchen.user`, which Livewire does not
     * re-apply to /livewire/update — so every write checks for itself.
     * Changing the recipe book is a manager's call, per kitchen.
     */
    public function toggleActive(int $id): void
    {
        $recipe = ProductionRecipe::findOrFail($id);
        abort_unless(Auth::user()?->canManageKitchen($recipe->kitchen_id), 403);

        $recipe->update(['is_active' => ! $recipe->is_active]);
        session()->flash('success', "Recipe \"{$recipe->name}\" " . ($recipe->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function deleteRecipe(int $id): void
    {
        $recipe = ProductionRecipe::findOrFail($id);
        abort_unless(Auth::user()?->canManageKitchen($recipe->kitchen_id), 403);

        $name   = $recipe->name;
        $recipe->delete();
        session()->flash('success', "Recipe \"{$name}\" deleted.");
    }

    // ── Render ──────────────────────────────────────────────────────────

    public function render()
    {
        $query = ProductionRecipe::with(['kitchen', 'yieldUom'])
            ->withCount('lines');

        if ($this->search) {
            $search = $this->search;
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")
            );
        }

        if ($this->kitchenFilter) {
            $query->where('kitchen_id', $this->kitchenFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        $recipes  = $query->orderBy('name')->paginate(15);
        $kitchens = CentralKitchen::active()->orderBy('name')->get();

        // Per-kitchen, because a recipe belongs to one kitchen and a manager of
        // one kitchen may be a chef in another. The blade reads these to hide
        // what the actions above would refuse anyway.
        $user        = Auth::user();
        $canCreate   = $user->canManageKitchen() || $kitchens->contains(fn ($k) => $user->canManageKitchen($k->id));
        $manageable  = $recipes->getCollection()
            ->pluck('kitchen_id')->unique()
            ->mapWithKeys(fn ($kid) => [(int) $kid => $user->canManageKitchen($kid ? (int) $kid : null)])
            ->all();

        return view('livewire.kitchen.production-recipes', compact('recipes', 'kitchens', 'canCreate', 'manageable'))
            ->layout('layouts.kitchen', ['title' => 'Production Recipes']);
    }
}
