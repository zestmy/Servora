<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeClaim;
use App\Models\Section;
use App\Services\Hr\OtClaimFilter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OtClaimPdfController extends Controller
{
    public function __invoke(Request $request, string $employee)
    {
        $user    = $request->user();
        $company = Company::find($user->company_id);

        $from = $request->input('from');
        $to   = $request->input('to');

        /*
         * Outlet, section and employment come off the print modal, which seeds
         * them from the list's own filters. Built through OtClaimFilter so the
         * synthetic employment options ("exclude outsourcing", "no status")
         * mean here exactly what they mean on the screen — this document is a
         * signable record, and a second implementation of those two is a
         * second set of answers to "who was left out".
         *
         * Status and employee are not filter fields here: the document is
         * approved-only by definition, and the employee is the route segment.
         */
        $filter = OtClaimFilter::fromScreen(
            status: '',
            from: (string) $from,
            to: (string) $to,
            employeeId: '',
            sectionId: (string) $request->input('section', ''),
            employmentStatus: (string) $request->input('employment', ''),
            outletId: (string) $request->input('outlet', ''),
        );

        // Same outlet scope as the Livewire component — cross-outlet roles see
        // all company outlets, everyone else only their assigned ones, and an
        // outlet arriving in the URL is not permission to read that branch.
        $availableOutletIds = $filter->outletScope($user->accessibleOutletIds());

        if ($employee === 'all') {
            // Every active employee the filter still leaves standing.
            $employees = Employee::with(['section', 'outlet'])
                ->whereIn('outlet_id', $availableOutletIds)
                ->where('is_active', true)
                ->when($filter->sectionId, fn ($q, $id) => $q->where('section_id', $id))
                ->tap(fn ($q) => $filter->applyEmploymentStatus($q))
                ->orderBy('name')
                ->get();

            $grouped = [];
            foreach ($employees as $emp) {
                // Filter claims by employee - use employee's outlet, not claim's outlet
                $query = OvertimeClaim::with(['employee', 'submitter', 'approver', 'outlet'])
                    ->where('employee_id', $emp->id)
                    ->where('status', 'approved');

                self::excludeTimeOff($query);

                if ($from) $query->where('claim_date', '>=', $from);
                if ($to)   $query->where('claim_date', '<=', $to);

                $claims = $query->orderBy('claim_date')->get();

                // Somebody whose approved OT is ALL time off has nothing
                // payable in this period, so they get no page at all — which
                // is the point of leaving time off out of this document.
                if ($claims->isEmpty()) continue;

                // Time-off hours dropped from this page. Reported in the
                // footer for the same reason the pending and rejected hours
                // are: a total that is quietly short is the one somebody
                // queries against their payslip.
                $timeOffHours = self::timeOffHours($emp->id, $from, $to);

                $submitters = $claims->pluck('submitter')->filter()->unique('id');

                // Hours still pending approval in this range — excluded from the
                // approved-only PDF but noted in the page footer.
                $pendingHours = (float) OvertimeClaim::where('employee_id', $emp->id)
                    ->where('status', 'submitted')
                    ->when($from, fn ($q) => $q->where('claim_date', '>=', $from))
                    ->when($to, fn ($q) => $q->where('claim_date', '<=', $to))
                    ->sum('total_ot_hours');

                // Rejected claims in this range — listed in the footer with the
                // rejector and their reason.
                $rejectedClaims = OvertimeClaim::with('approver')
                    ->where('employee_id', $emp->id)
                    ->where('status', 'rejected')
                    ->when($from, fn ($q) => $q->where('claim_date', '>=', $from))
                    ->when($to, fn ($q) => $q->where('claim_date', '<=', $to))
                    ->orderBy('claim_date')
                    ->get();

                $grouped[] = [
                    'employee'    => $emp,
                    'claims'      => $claims,
                    'totalHours'  => $claims->sum('total_ot_hours'),
                    'hoursByType' => $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours')),
                    // Which of these hours become money and which become time
                    // off — see the single-employee branch below for why.
                    'hoursBySettlement' => $claims->groupBy('settlement')->map(fn ($g) => $g->sum('total_ot_hours')),
                    'submitters'  => $submitters,
                    // Actual approver(s) who approved these claims, not everyone with privilege.
                    'approvers'   => $claims->pluck('approver')->filter()->unique('id'),
                    'pendingHours' => $pendingHours,
                    'rejectedClaims' => $rejectedClaims,
                    'timeOffHours' => $timeOffHours,
                ];
            }

            // Calendar events (public holidays, etc.) covering the claim dates.
            $allClaims      = collect($grouped)->pluck('claims')->flatten();
            $calendarEvents = CalendarEvent::coveringRange(
                $availableOutletIds,
                $from ?: $allClaims->min('claim_date')?->toDateString(),
                $to   ?: $allClaims->max('claim_date')?->toDateString(),
            );

            $pdf = Pdf::loadView('pdf.ot-claims-all', [
                'company'        => $company,
                'grouped'        => $grouped,
                'calendarEvents' => $calendarEvents,
                'from'           => $from,
                'to'             => $to,
                // Only reaches the page when nothing matched: "no claims found"
                // reads as "nobody worked OT" unless it says what was excluded.
                'narrowedBy'     => $this->describeNarrowing($filter),
            ])->setPaper('a4', 'portrait');

            return $pdf->download('ot-claims-all.pdf');
        }

        /*
         * Single employee. Section and employment are not re-applied here —
         * they narrow WHICH employees get a page, and naming one is a narrower
         * answer to the same question. The modal keeps the picker in step with
         * them, so a contradictory pair cannot be built from the UI anyway.
         */
        $employee = Employee::with(['section', 'outlet'])
            ->whereIn('outlet_id', $availableOutletIds)
            ->findOrFail((int) $employee);

        // Get all approved claims for this employee (no outlet filter on claims)
        $query = OvertimeClaim::with(['employee', 'submitter', 'approver', 'outlet'])
            ->where('employee_id', $employee->id)
            ->where('status', 'approved');

        self::excludeTimeOff($query);

        if ($from) $query->where('claim_date', '>=', $from);
        if ($to)   $query->where('claim_date', '<=', $to);

        $claims = $query->orderBy('claim_date')->get();

        $timeOffHours = self::timeOffHours($employee->id, $from, $to);

        $totalHours  = $claims->sum('total_ot_hours');
        $hoursByType = $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours'));

        /*
         * Split by how each claim is settled. Both halves are approved
         * overtime and both belong on this record — the person worked those
         * hours either way — but only one half will ever appear on a payslip,
         * and a total that silently mixes them is the one number somebody will
         * check their pay against.
         */
        $hoursBySettlement = $claims->groupBy('settlement')->map(fn ($g) => $g->sum('total_ot_hours'));

        // Hours still pending approval in this range — excluded from the PDF
        // (which is approved-only), but surfaced as a footer note so the total
        // never looks short without explanation.
        $pendingHours = (float) OvertimeClaim::where('employee_id', $employee->id)
            ->where('status', 'submitted')
            ->when($from, fn ($q) => $q->where('claim_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('claim_date', '<=', $to))
            ->sum('total_ot_hours');

        // Rejected claims in this range — also excluded, listed in the footer
        // with the rejector and their reason for the record.
        $rejectedClaims = OvertimeClaim::with('approver')
            ->where('employee_id', $employee->id)
            ->where('status', 'rejected')
            ->when($from, fn ($q) => $q->where('claim_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('claim_date', '<=', $to))
            ->orderBy('claim_date')
            ->get();

        // Unique submitters
        $submitters = $claims->pluck('submitter')->filter()->unique('id');

        // Actual approver(s) who approved these claims, not everyone with privilege.
        $approvers = $claims->pluck('approver')->filter()->unique('id');

        // Calendar events (public holidays, etc.) covering the claim dates.
        $calendarEvents = CalendarEvent::coveringRange(
            $availableOutletIds,
            $from ?: $claims->min('claim_date')?->toDateString(),
            $to   ?: $claims->max('claim_date')?->toDateString(),
        );

        $pdf = Pdf::loadView('pdf.ot-claims', compact(
            'company', 'employee', 'claims', 'totalHours', 'hoursByType', 'hoursBySettlement', 'submitters', 'approvers', 'calendarEvents', 'from', 'to', 'pendingHours', 'rejectedClaims', 'timeOffHours'
        ))->setPaper('a4', 'portrait');

        $name = str_replace([' ', '/', '\\'], '-', strtolower($employee->name));

        return $pdf->download("ot-claims-{$name}.pdf");
    }

    /**
     * Leave time-off overtime out of this document.
     *
     * The form is what payroll pays against, and hours settled as time off
     * never reach a payslip — they are taken back as leave instead. Printing
     * them beside payable hours put a number on a signed page that nothing
     * downstream would honour.
     *
     * Excluding time off rather than selecting payroll, so the column's own
     * default does the right thing: `settlement` is NOT NULL defaulting to
     * 'payroll', which is what every claim written before the column existed
     * became. Nothing has to be back-filled for a historical form to keep
     * printing exactly what it printed before.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<OvertimeClaim>  $query
     */
    private static function excludeTimeOff($query): void
    {
        $query->where('settlement', '!=', OvertimeClaim::SETTLE_TIME_OFF);
    }

    /** Approved hours taken as time off in the range — excluded, but stated. */
    private static function timeOffHours(int $employeeId, ?string $from, ?string $to): float
    {
        return (float) OvertimeClaim::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('settlement', OvertimeClaim::SETTLE_TIME_OFF)
            ->when($from, fn ($q) => $q->where('claim_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('claim_date', '<=', $to))
            ->sum('total_ot_hours');
    }

    /**
     * The section/employment narrowing in words, for the empty state.
     *
     * @return array<int, string>
     */
    private function describeNarrowing(OtClaimFilter $filter): array
    {
        $parts = [];

        if ($filter->sectionId !== null) {
            $parts[] = 'Section: ' . (Section::find($filter->sectionId)?->name ?? 'Unknown');
        }

        if (($employment = $filter->employmentLabel()) !== null) {
            $parts[] = 'Employment: ' . $employment;
        }

        return $parts;
    }
}
