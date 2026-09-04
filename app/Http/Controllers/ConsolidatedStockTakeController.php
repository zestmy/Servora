<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\StockTake;
use App\Services\StockTakeConsolidator;
use App\Traits\ScopesToActiveOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The counts in a range and department, filed as one inventory.
 *
 * Filters arrive in the query string because the screen owns them: the button
 * hands over exactly what the table is showing, so the file matches what was on
 * screen when it was asked for. None of them are trusted — the outlet goes
 * through the same accessible-outlets check the listing uses, so a hand-edited
 * id narrows the report or does nothing, and never widens it.
 */
class ConsolidatedStockTakeController extends Controller
{
    use ScopesToActiveOutlet;

    public function __invoke(Request $request, StockTakeConsolidator $consolidator)
    {
        [$report, $company, $scope, $excludedDrafts, $from, $to] = $this->load($request, $consolidator);

        $pdf = Pdf::loadView('pdf.consolidated-stock-take', compact('report', 'company', 'scope', 'excludedDrafts'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('Consolidated-Inventory-' . $from . '-to-' . $to . '.pdf');
    }

    /**
     * The consolidated report, its company and scope labels, and the filename
     * pieces — shared by the PDF and the workbook so the two can never drift
     * into disagreeing about the same range.
     *
     * @return array{0: array, 1: ?Company, 2: array<string, string>, 3: int, 4: string, 5: string}
     */
    protected function load(Request $request, StockTakeConsolidator $consolidator): array
    {
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $department = $request->query('department', '');
        $search     = trim((string) $request->query('search', ''));

        // Everything the range covers, whatever state it is in. Status is the
        // only thing that separates what goes in the file from what is merely
        // counted towards it, so it is applied last, twice.
        $inRange = function () use ($request, $from, $to, $department, $search) {
            $query = StockTake::query()
                ->whereBetween('stock_take_date', [$from, $to])
                ->where('method', 'detailed');

            $this->scopeByOutletFilter($query, $request->query('outlet'));

            if ($department === 'none') {
                $query->whereNull('department_id');
            } elseif ($department !== '' && $department !== null) {
                $query->where('department_id', (int) $department);
            }

            if ($search !== '') {
                $query->where('reference_number', 'like', '%' . $search . '%');
            }

            return $query;
        };

        // Completed only. A file for the cabinet should be of counts somebody
        // finished, not of sheets still being walked around a chiller — those
        // change after the PDF is printed.
        $stockTakes = $inRange()
            ->where('status', 'completed')
            ->with(['lines', 'outlet', 'department'])
            ->orderBy('stock_take_date')
            ->orderBy('id')
            ->get();

        $report  = $consolidator->consolidate($stockTakes);
        $company = Company::find(Auth::user()->company_id);

        // The screen counts drafts and this file does not, so it says how many
        // it left behind rather than letting the reader wonder where they went.
        $excludedDrafts = $inRange()->where('status', '!=', 'completed')->count();

        $scope = [
            'from'       => $from,
            'to'         => $to,
            'outlet'     => ($id = $this->selectedOutletId($request->query('outlet')))
                ? Outlet::find($id)?->name
                : 'All outlets',
            'department' => $department === 'none'
                ? 'No department'
                : ($department !== '' && $department !== null
                    ? (Department::find((int) $department)?->name ?? '—')
                    : 'All departments'),
        ];

        return [$report, $company, $scope, $excludedDrafts, $from, $to];
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
