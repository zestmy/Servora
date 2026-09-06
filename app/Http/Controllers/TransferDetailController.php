<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Outlet;
use App\Models\OutletTransfer;
use App\Services\TransferConsolidator;
use App\Traits\ScopesToActiveOutlet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Every item moved in a range, merged into one report and filed as a PDF.
 *
 * The Transfer Summary export answers "which outlet sent the most value";
 * this answers the question underneath it — exactly what moved, and how
 * much of it. Same relationship WastageDetailController has to
 * WastageSummaryController.
 *
 * Filters arrive in the query string because the screen owns them — see
 * ConsolidatedStockTakeController's doc comment for why none of them are
 * trusted as-is.
 */
class TransferDetailController extends Controller
{
    use ScopesToActiveOutlet;

    public function __invoke(Request $request, TransferConsolidator $consolidator)
    {
        [$report, $company, $scope] = $this->load($request, $consolidator);

        $pdf = Pdf::loadView('pdf.transfer-detail', compact('report', 'company', 'scope'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('Transfer-Details-' . $scope['from'] . '-to-' . $scope['to'] . '.pdf');
    }

    /**
     * The consolidated report, its company and its scope labels — shared by
     * the PDF and the workbook so the two can never drift into disagreeing
     * about the same range.
     *
     * @return array{0: array, 1: ?Company, 2: array<string, string>}
     */
    protected function load(Request $request, TransferConsolidator $consolidator): array
    {
        $from = $this->date($request->query('from')) ?? now()->startOfMonth()->toDateString();
        $to   = $this->date($request->query('to'))   ?? now()->toDateString();

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $status = (string) $request->query('status', '');
        $search = trim((string) $request->query('search', ''));

        $query = OutletTransfer::query()->whereBetween('transfer_date', [$from, $to]);

        // A transfer belongs to both the outlet that sent it and the one
        // receiving — same dual-column scoping App\Livewire\Inventory\Index::filtered()
        // uses for the transfers tab.
        $ids = $this->selectedOutletId($request->query('outlet'))
            ? [$this->selectedOutletId($request->query('outlet'))]
            : $this->availableOutletIds();

        if (! empty($ids)) {
            $query->where(fn ($q) => $q->whereIn('from_outlet_id', $ids)->orWhereIn('to_outlet_id', $ids));
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where('transfer_number', 'like', '%' . $search . '%');
        }

        $transfers = $query
            ->with(['lines.ingredient.baseUom', 'lines.ingredient.recipeUom', 'lines.ingredient.ingredientCategory.parent', 'fromOutlet', 'toOutlet'])
            ->orderBy('transfer_date')
            ->orderBy('id')
            ->get();

        $report  = $consolidator->consolidate($transfers);
        $company = Company::find(Auth::user()->company_id);

        $scope = [
            'from'   => $from,
            'to'     => $to,
            'outlet' => ($id = $this->selectedOutletId($request->query('outlet')))
                ? Outlet::find($id)?->name
                : 'All outlets',
            'status' => $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'All statuses',
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
