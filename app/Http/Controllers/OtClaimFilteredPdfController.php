<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\OvertimeClaim;
use App\Services\Hr\OtClaimFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * The Overtime Claims list, printed as it appears on screen.
 *
 * THE SECOND OF TWO EXPORTS, deliberately alongside the first rather than
 * replacing it. OtClaimPdfController prints a per-employee CLAIM FORM of
 * approved hours — one page each, signature blocks, the thing that gets filed.
 * This one prints the LIST: the same rows, the same columns, the same order,
 * for whatever the screen is currently filtered to. Two different documents
 * answering two different questions.
 *
 * Because it reproduces a list rather than certifying hours, it carries what
 * the list carries and the claim form has no room for — who submitted each
 * claim and when, and the status of every row.
 *
 * Not paginated. The screen shows a page at a time; the export is the whole
 * filtered set, because printing page three of nine is nobody's intention.
 */
class OtClaimFilteredPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $user    = $request->user();
        $company = Company::find($user->company_id);

        $filter    = OtClaimFilter::fromRequest($request);
        $outletIds = $user->accessibleOutletIds();

        $query = OvertimeClaim::with(['employee.section', 'employee.outlet', 'submitter', 'approver']);

        $filter->apply($query, $outletIds);
        $filter->applySort($query);

        $claims = $query->get();

        // Holidays and events for the date column, exactly as the screen
        // annotates it. Scoped to the outlets in view.
        $calendarEvents = CalendarEvent::coveringRange(
            $filter->outletScope($outletIds),
            $filter->from ?: $claims->min('claim_date')?->toDateString(),
            $filter->to   ?: $claims->max('claim_date')?->toDateString(),
        );

        $pdf = Pdf::loadView('pdf.ot-claims-filtered', [
            'company'        => $company,
            'claims'         => $claims,
            'calendarEvents' => $calendarEvents,
            'filter'         => $filter,
            'filterSummary'  => $filter->describe(),
            // Broken out because this document can span statuses, and one
            // total mixing approved with rejected hours is a number that means
            // nothing to whoever reads it.
            'hoursByStatus'  => $claims->groupBy('status')->map(fn ($g) => $g->sum('total_ot_hours')),
            'totalHours'     => $claims->sum('total_ot_hours'),
            'generatedBy'    => $user->name,
        ])
            // Landscape: seven columns including a reason, which is the column
            // that gets squeezed into unreadability on portrait.
            ->setPaper('a4', 'landscape');

        return $pdf->download('ot-claims-' . now()->format('Ymd-His') . '.pdf');
    }
}
