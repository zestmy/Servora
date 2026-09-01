<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\StockTake;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

/**
 * A finished count, as a document.
 *
 * The count sheet is the blank that goes onto the shelf; this is what comes
 * back — what was expected, what was found, what the difference was worth. Only
 * for a completed count: a draft is still being worked on, and filing one as a
 * result would put a number nobody has stood behind into a folder.
 *
 * The PDF and the workbook are built from the same loader below so the two can
 * never drift into disagreeing about the same count.
 */
class StockTakeResultController extends Controller
{
    public function __invoke(int $id)
    {
        [$stockTake, $groups, $totals] = $this->result($id);

        $company = Company::find(Auth::user()->company_id);

        $pdf = Pdf::loadView('pdf.stock-take-result', compact('stockTake', 'company', 'groups', 'totals'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('Stock-Take-' . $this->reference($stockTake) . '.pdf');
    }

    /**
     * The count, its lines grouped by category, and the totals they add to.
     *
     * @return array{0: StockTake, 1: array<int, array<string, mixed>>, 2: array<string, mixed>}
     */
    protected function result(int $id): array
    {
        $stockTake = StockTake::with([
            'outlet', 'department', 'createdBy',
            'lines.uom',
            'lines.ingredient.baseUom',
            'lines.ingredient.ingredientCategory.parent',
        ])->findOrFail($id);

        // The list only offers this on a completed row; the route is checked
        // again because a URL is not a button.
        abort_unless($stockTake->status === 'completed', 404);

        $groups = [];
        $totals = ['items' => 0, 'value' => 0.0, 'variance' => 0.0, 'over' => 0, 'short' => 0];

        foreach ($stockTake->lines as $line) {
            $ingredient = $line->ingredient;
            $category   = $ingredient?->ingredientCategory;
            $name       = $category?->parent?->name ?? $category?->name ?? 'Uncategorized';

            $counted  = (float) $line->actual_quantity;
            $system   = (float) $line->system_quantity;
            $unitCost = (float) $line->unit_cost;
            $variance = $counted - $system;

            $row = [
                'name'          => $ingredient?->name ?? '(Deleted item)',
                'code'          => $ingredient?->code,
                'sub'           => $category?->parent ? $category->name : null,
                'uom'           => $line->uom?->abbreviation ?? '',
                'system'        => $system,
                'counted'       => $counted,
                'variance'      => $variance,
                'unit_cost'     => $unitCost,
                'value'         => round($counted * $unitCost, 2),
                'variance_cost' => round($variance * $unitCost, 2),
            ];

            $groups[$name]['name'] ??= $name;
            $groups[$name]['items'][] = $row;
            $groups[$name]['value'] = ($groups[$name]['value'] ?? 0) + $row['value'];

            $totals['items']++;
            $totals['value']    += $row['value'];
            $totals['variance'] += $row['variance_cost'];

            if ($variance > 0.0001)  $totals['over']++;
            if ($variance < -0.0001) $totals['short']++;
        }

        foreach ($groups as $key => $group) {
            usort($group['items'], fn ($a, $b) => strcmp($a['name'], $b['name']));
            $groups[$key]['items'] = $group['items'];
            $groups[$key]['value'] = round($group['value'], 2);
        }

        // Uncategorised last: it is a gap in the catalogue, not a category.
        uksort($groups, function ($a, $b) {
            if ($a === 'Uncategorized') return 1;
            if ($b === 'Uncategorized') return -1;
            return strcmp($a, $b);
        });

        $totals['value']    = round($totals['value'], 2);
        $totals['variance'] = round($totals['variance'], 2);

        return [$stockTake, array_values($groups), $totals];
    }

    protected function reference(StockTake $stockTake): string
    {
        $reference = $stockTake->reference_number ?: 'ST-' . $stockTake->id;

        return str_replace(['/', '\\', '%', ':', '*', '?', '"', '<', '>', '|'], '-', $reference);
    }
}
