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
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $query = StockTake::query()
            ->whereBetween('stock_take_date', [$from, $to])
            ->where('method', 'detailed');

        $this->scopeByOutletFilter($query, $request->query('outlet'));

        $department = $request->query('department', '');
        if ($department === 'none') {
            $query->whereNull('department_id');
        } elseif ($department !== '' && $department !== null) {
            $query->where('department_id', (int) $department);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where('reference_number', 'like', '%' . $search . '%');
        }

        $stockTakes = $query
            ->with(['lines', 'outlet', 'department'])
            ->orderBy('stock_take_date')
            ->orderBy('id')
            ->get();

        $report  = $consolidator->consolidate($stockTakes);
        $company = Company::find(Auth::user()->company_id);

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

        $pdf = Pdf::loadView('pdf.consolidated-stock-take', compact('report', 'company', 'scope'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('Consolidated-Inventory-' . $from . '-to-' . $to . '.pdf');
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
