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
            'lines.uom',
            'lines.ingredient.baseUom',
            'lines.ingredient.recipeUom',
            'lines.ingredient.uomConversions',
            'lines.ingredient.ingredientCategory.parent',
            'createdBy',
        ])->findOrFail($id);

        // Company-scoped by the model; the outlet is checked here too.
        abort_unless(Auth::user()->canAccessOutlet((int) $stockTake->outlet_id), 404);

        $groupedLines = $stockTake->lines
            ->sortBy(fn ($l) => ($l->ingredient?->ingredientCategory?->parent?->name ?? $l->ingredient?->ingredientCategory?->name ?? 'ZZZ') . $l->ingredient?->name)
            ->groupBy(function ($line) {
                $cat = $line->ingredient?->ingredientCategory;
                $parent = $cat?->parent;
                return $parent ? $parent->name : ($cat ? $cat->name : 'Uncategorized');
            });

        $pdf = Pdf::loadView('pdf.stock-take-count-sheet', compact('stockTake', 'company', 'groupedLines'))
            ->setPaper('a4', 'portrait');

        // References are free text ("ST/2026/09"); Content-Disposition refuses
        // "/" and "\" in a filename, which 500'd the download.
        $ref = str_replace(['/', '\\', '%', ':', '*', '?', '"', '<', '>', '|'], '-', $stockTake->reference_number ?? 'ST-' . $stockTake->id);
        return $pdf->download("Count-Sheet-{$ref}.pdf");
    }
}
