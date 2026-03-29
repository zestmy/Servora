<?php

namespace App\Livewire\Reports\Menu;

use App\Models\IngredientCategory;
use App\Models\Recipe;
use App\Traits\ReportFilters;
use App\Traits\ScopesToActiveOutlet;
use Livewire\Component;
use Livewire\WithPagination;

class MenuIngredients extends Component
{
    use WithPagination, ReportFilters, ScopesToActiveOutlet;

    public ?int $categoryFilter = null;

    public function mount(): void
    {
        $this->mountReportFilters();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function exportCsv()
    {
        $recipes = $this->buildQuery()->get();
        $rows = [];

        foreach ($recipes as $recipe) {
            foreach ($recipe->lines as $line) {
                $rows[] = [
                    $recipe->name,
                    $recipe->ingredientCategory?->name ?? '-',
                    $line->ingredient?->name ?? '-',
                    $line->quantity,
                    $line->uom?->abbreviation ?? '-',
                    number_format($line->line_total_cost, 4),
                ];
            }
        }

        return $this->exportCsvDownload('menu-ingredients.csv', [
            'Recipe', 'Category', 'Ingredient', 'Quantity', 'UOM', 'Cost',
        ], $rows);
    }

    public function render()
    {
        $recipes = $this->buildQuery()->paginate(25);
        $outlets = $this->getOutlets();
        $categories = IngredientCategory::roots()->active()->ordered()->with('children')->get();

        return view('livewire.reports.menu.menu-ingredients', compact('recipes', 'outlets', 'categories'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Menu & Ingredients']);
    }

    private function buildQuery()
    {
        return Recipe::query()
            ->with(['lines.ingredient', 'lines.uom', 'ingredientCategory'])
            ->where('is_active', true)
            ->when($this->categoryFilter, function ($q) {
                $cat = IngredientCategory::with('children')->find($this->categoryFilter);
                if ($cat) {
                    $ids = $cat->children->isNotEmpty()
                        ? $cat->children->pluck('id')->push($cat->id)->toArray()
                        : [$cat->id];
                    $q->whereIn('ingredient_category_id', $ids);
                }
            })
            ->orderBy('name');
    }
}
