<?php

namespace App\Livewire\Purchasing;

use App\Models\Ingredient;
use App\Services\PriceMonitoringService;
use Livewire\Component;

class PriceComparison extends Component
{
    public string $ingredientSearch = '';
    public ?int $selectedIngredientId = null;
    public ?string $selectedIngredientName = null;
    public array $comparisonData = [];
    public array $recentChanges = [];

    public function mount(): void
    {
        $this->recentChanges = PriceMonitoringService::getRecentPriceChanges(30, 5.0)->toArray();
    }

    public function selectIngredient(int $id): void
    {
        $ingredient = Ingredient::find($id);
        if (! $ingredient) return;

        $this->selectedIngredientId = $id;
        $this->selectedIngredientName = $ingredient->name;
        $this->ingredientSearch = '';

        $this->comparisonData = PriceMonitoringService::compareSupplierPrices($id)->toArray();
    }

    public function clearSelection(): void
    {
        $this->selectedIngredientId = null;
        $this->selectedIngredientName = null;
        $this->comparisonData = [];
    }

    public function render()
    {
        $searchResults = collect();
        if (strlen($this->ingredientSearch) >= 2) {
            $searchResults = Ingredient::where('is_active', true)
                ->where(fn ($q) => $q->where('name', 'like', '%' . $this->ingredientSearch . '%')
                    ->orWhere('code', 'like', '%' . $this->ingredientSearch . '%'))
                ->orderBy('name')
                ->limit(10)
                ->get();
        }

        return view('livewire.purchasing.price-comparison', compact('searchResults'))
            ->layout('layouts.app', ['title' => 'Price Comparison']);
    }
}
