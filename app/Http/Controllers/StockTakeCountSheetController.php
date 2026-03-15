<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\StockTake;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockTakeCountSheetController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        $company = Company::find(Auth::user()->company_id);

        $stockTake = StockTake::with([
            'outlet',
            'department',
            'lines.ingredient.baseUom',
            'lines.ingredient.ingredientCategory.parent',
            'createdBy',
        ])->findOrFail($id);

        $groupedLines = $stockTake->lines
            ->sortBy(fn ($l) => ($l->ingredient?->ingredientCategory?->parent?->name ?? $l->ingredient?->ingredientCategory?->name ?? 'ZZZ') . $l->ingredient?->name)
            ->groupBy(function ($line) {
                $cat = $line->ingredient?->ingredientCategory;
                $parent = $cat?->parent;
                return $parent ? $parent->name : ($cat ? $cat->name : 'Uncategorized');
            });

        $pdf = Pdf::loadView('pdf.stock-take-count-sheet', compact('stockTake', 'company', 'groupedLines'))
            ->setPaper('a4', 'portrait');

        $ref = $stockTake->reference_number ?? 'ST-' . $stockTake->id;
        return $pdf->download("Count-Sheet-{$ref}.pdf");
    }
}
