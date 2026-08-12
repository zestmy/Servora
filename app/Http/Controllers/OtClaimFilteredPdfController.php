<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeClaim;
use App\Services\Hr\OtClaimFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * An overtime statement for whatever the Overtime Claims screen is currently
 * showing.
 *
 * THE SECOND OF TWO EXPORTS, deliberately alongside the first rather than
 * replacing it. OtClaimPdfController prints APPROVED claims for a date range
 * and is what gets signed and filed; people rely on it meaning exactly that.
 * This one answers a different question — "print what I am looking at" —
 * including statuses the approved-only document will never show: the pending
 * queue, the rejected ones, or everything at once.
 *
 * Same shape as the existing statement: grouped per employee, one page each,
 * so the two documents read alike and nobody has to learn a second layout.
 *
 * The filters come from OtClaimFilter, the same object the screen itself
 * applies. That is the whole point — a document headed "matches your current
 * filter" that used its own rules would be worse than no document, because
 * somebody would sign it believing it was the list on screen.
 */
class OtClaimFilteredPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $user    = $request->user();
        $company = Company::find($user->company_id);

        $filter    = OtClaimFilter::fromRequest($request);
        $outletIds = $user->accessibleOutletIds();
        $scoped    = $filter->outletScope($outletIds);

        // One query for every matching claim, then grouped in memory. The
        // alternative — a query per employee, as the approved-only export does
        // — is a query per head for a document that may cover the whole
        // company once the status filter is widened.
        $claims = $filter
            ->apply(
                OvertimeClaim::with(['employee.section', 'employee.outlet', 'submitter', 'approver']),
                $outletIds,
            )
            ->orderBy('claim_date')
            ->get();

        $grouped = $claims
            ->filter(fn ($c) => $c->employee !== null)
            ->groupBy('employee_id')
            ->map(fn ($rows) => [
                'employee'    => $rows->first()->employee,
                'claims'      => $rows,
                'totalHours'  => $rows->sum('total_ot_hours'),
                'hoursByType' => $rows->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours')),
                // Money or time off. Both are approved overtime and both belong
                // on the record, but only one half reaches a payslip, and a
                // total that mixes them is the figure somebody checks their pay
                // against.
                'hoursBySettlement' => $rows->groupBy('settlement')->map(fn ($g) => $g->sum('total_ot_hours')),
                // Broken out because this document can span statuses: a single
                // "total hours" across approved and rejected claims would be a
                // number that means nothing.
                'hoursByStatus' => $rows->groupBy('status')->map(fn ($g) => $g->sum('total_ot_hours')),
                'submitters'  => $rows->pluck('submitter')->filter()->unique('id'),
                'approvers'   => $rows->pluck('approver')->filter()->unique('id'),
            ])
            ->sortBy(fn ($g) => $g['employee']->name)
            ->values()
            ->all();

        $calendarEvents = CalendarEvent::coveringRange(
            $scoped,
            $filter->from ?: $claims->min('claim_date')?->toDateString(),
            $filter->to   ?: $claims->max('claim_date')?->toDateString(),
        );

        $pdf = Pdf::loadView('pdf.ot-claims-filtered', [
            'company'        => $company,
            'grouped'        => $grouped,
            'calendarEvents' => $calendarEvents,
            'filter'         => $filter,
            'filterSummary'  => $filter->describe(),
            // Shown per claim only when the document spans more than one
            // status — a status column on a single-status statement is a
            // column of the same word repeated.
            'showStatus'     => $filter->status === null,
            'totalHours'     => $claims->sum('total_ot_hours'),
            'claimCount'     => $claims->count(),
            'from'           => $filter->from,
            'to'             => $filter->to,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('ot-statement-' . now()->format('Ymd-His') . '.pdf');
    }
}
