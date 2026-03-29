<?php

namespace App\Livewire\Settings;

use App\Models\Ingredient;
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
    public array $parLevels = []; // [ingredient_id => par_level_value]

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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function saveAll(): void
    {
        $companyId = Auth::user()->company_id;

        foreach ($this->parLevels as $ingredientId => $value) {
            $parLevel = floatval($value);

            if ($parLevel > 0) {
                IngredientParLevel::updateOrCreate(
                    ['ingredient_id' => $ingredientId, 'outlet_id' => $this->outletId],
                    ['par_level' => $parLevel, 'company_id' => $companyId]
                );
            } else {
                IngredientParLevel::where('ingredient_id', $ingredientId)
                    ->where('outlet_id', $this->outletId)
                    ->delete();
            }
        }

        session()->flash('success', 'Par levels saved.');
    }

    public function render()
    {
        $outlets = Outlet::where('company_id', Auth::user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $query = Ingredient::with('baseUom')
            ->where('is_active', true)
            ->orderBy('name');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('code', 'like', '%' . $this->search . '%');
            });
        }

        $ingredients = $query->paginate(50);

        return view('livewire.settings.par-levels', compact('outlets', 'ingredients'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Par Levels']);
    }

    private function loadParLevels(): void
    {
        if (!$this->outletId) {
            $this->parLevels = [];
            return;
        }

        $this->parLevels = IngredientParLevel::where('outlet_id', $this->outletId)
            ->pluck('par_level', 'ingredient_id')
            ->map(fn ($v) => (string) floatval($v))
            ->toArray();
    }
}
