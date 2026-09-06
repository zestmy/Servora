<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\StaffMealRecord;
use App\Services\StaffMealConsolidator;
use App\Traits\ScopesToActiveOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Every item served to staff in a range, merged into one report and filed as a PDF.
 *
 * The Staff Meal Summary export answers "which outlet fed staff the most
 * money"; this answers the question underneath it — exactly what was served,
 * and how much of it. Same relationship WastageDetailController has to
 * WastageSummaryController.
 *
 * Filters arrive in the query string because the screen owns them — see
 * ConsolidatedStockTakeController's doc comment for why none of them are
 * trusted as-is.
 */
class StaffMealDetailController extends Controller
{
    use ScopesToActiveOutlet;

    public function __invoke(Request $request, StaffMealConsolidator $consolidator)
    {
        [$report, $company, $scope] = $this->load($request, $consolidator);

        $pdf = Pdf::loadView('pdf.staff-meal-detail', compact('report', 'company', 'scope'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('Staff-Meal-Details-' . $scope['from'] . '-to-' . $scope['to'] . '.pdf');
    }

    /**
     * The consolidated report, its company and its scope labels — shared by
     * the PDF and the workbook so the two can never drift into disagreeing
     * about the same range.
     *
     * @return array{0: array, 1: ?Company, 2: array<string, string>}
     */
    protected function load(Request $request, StaffMealConsolidator $consolidator): array
    {
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $search = trim((string) $request->query('search', ''));

        $query = StaffMealRecord::query()->whereBetween('meal_date', [$from, $to]);
        $this->scopeByOutletFilter($query, $request->query('outlet'));

        if ($search !== '') {
            $query->where('reference_number', 'like', '%' . $search . '%');
        }

        $records = $query
            ->with([
                'lines.ingredient.baseUom', 'lines.ingredient.recipeUom', 'lines.ingredient.ingredientCategory.parent',
                'lines.recipe.yieldUom',
                'outlet',
            ])
            ->orderBy('meal_date')
            ->orderBy('id')
            ->get();

        $report  = $consolidator->consolidate($records);
        $company = Company::find(Auth::user()->company_id);

        $scope = [
            'from'   => $from,
            'to'     => $to,
            'outlet' => ($id = $this->selectedOutletId($request->query('outlet')))
                ? Outlet::find($id)?->name
                : 'All outlets',
        ];

        return [$report, $company, $scope];
    }

    /** A date we can use, or nothing — never an exception from a hand-typed URL. */
    private function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
