<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Services\CsvExportService;
use Illuminate\Http\Request;

class IngredientExportController extends Controller
{
    public function export()
    {
        $ingredients = Ingredient::with(['baseUom', 'recipeUom', 'ingredientCategory.parent'])
            ->orderBy('name')
            ->get();

        $headers = [
            'ID', 'Name', 'Code', 'Category', 'Base UOM', 'Recipe UOM',
            'Purchase Price', 'Yield %', 'Is Active',
        ];

        $rows = $ingredients->map(function ($ing) {
            $catLabel = '';
            if ($ing->ingredientCategory) {
                $catLabel = $ing->ingredientCategory->parent
                    ? $ing->ingredientCategory->parent->name . ' / ' . $ing->ingredientCategory->name
                    : $ing->ingredientCategory->name;
            }

            return [
                $ing->id,
                $ing->name,
                $ing->code ?? '',
                $catLabel,
                $ing->baseUom?->abbreviation ?? '',
                $ing->recipeUom?->abbreviation ?? '',
                $ing->purchase_price,
                $ing->yield_percent,
                $ing->is_active ? 'Yes' : 'No',
            ];
        });

        return CsvExportService::download(
            'ingredients-' . now()->format('Y-m-d') . '.csv',
            $headers,
            $rows
        );
    }
}
