<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeClaim;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class OtClaimPdfController extends Controller
{
    public function __invoke(Request $request, string $employee)
    {
        $user    = $request->user();
        $company = Company::find($user->company_id);

        // Use same outlet scope as the Livewire component — cross-outlet roles
        // see all company outlets; everyone else sees only assigned outlets.
        $availableOutletIds = $user->canViewAllOutlets()
            ? \App\Models\Outlet::where('company_id', $user->company_id)->pluck('id')->all()
            : $user->outlets()->pluck('outlets.id')->all();

        // If outlet filter is specified, narrow down to that outlet only
        $outletFilter = $request->input('outlet');
        if ($outletFilter && in_array((int) $outletFilter, $availableOutletIds)) {
            $availableOutletIds = [(int) $outletFilter];
        }

        $from = $request->input('from');
        $to   = $request->input('to');

        if ($employee === 'all') {
            // Get all active employees from outlets the user can access
            $employees = Employee::with(['section', 'outlet'])
                ->whereIn('outlet_id', $availableOutletIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $grouped = [];
            foreach ($employees as $emp) {
                // Filter claims by employee - use employee's outlet, not claim's outlet
                $query = OvertimeClaim::with(['employee', 'submitter', 'approver', 'outlet'])
                    ->where('employee_id', $emp->id)
                    ->where('status', 'approved');

                if ($from) $query->where('claim_date', '>=', $from);
                if ($to)   $query->where('claim_date', '<=', $to);

                $claims = $query->orderBy('claim_date')->get();
                if ($claims->isEmpty()) continue;

                $submitters = $claims->pluck('submitter')->filter()->unique('id');

                $grouped[] = [
                    'employee'    => $emp,
                    'claims'      => $claims,
                    'totalHours'  => $claims->sum('total_ot_hours'),
                    'hoursByType' => $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours')),
                    'submitters'  => $submitters,
                    // Actual approver(s) who approved these claims, not everyone with privilege.
                    'approvers'   => $claims->pluck('approver')->filter()->unique('id'),
                ];
            }

            // Calendar events (public holidays, etc.) covering the claim dates.
            $allClaims      = collect($grouped)->pluck('claims')->flatten();
            $calendarEvents = CalendarEvent::coveringRange(
                $availableOutletIds,
                $from ?: $allClaims->min('claim_date')?->toDateString(),
                $to   ?: $allClaims->max('claim_date')?->toDateString(),
            );

            $pdf = Pdf::loadView('pdf.ot-claims-all', compact(
                'company', 'grouped', 'calendarEvents', 'from', 'to'
            ))->setPaper('a4', 'portrait');

            return $pdf->download('ot-claims-all.pdf');
        }

        // Single employee - verify they belong to an accessible outlet
        $employee = Employee::with(['section', 'outlet'])
            ->whereIn('outlet_id', $availableOutletIds)
            ->findOrFail((int) $employee);

        // Get all approved claims for this employee (no outlet filter on claims)
        $query = OvertimeClaim::with(['employee', 'submitter', 'approver', 'outlet'])
            ->where('employee_id', $employee->id)
            ->where('status', 'approved');

        if ($from) $query->where('claim_date', '>=', $from);
        if ($to)   $query->where('claim_date', '<=', $to);

        $claims = $query->orderBy('claim_date')->get();

        $totalHours  = $claims->sum('total_ot_hours');
        $hoursByType = $claims->groupBy('ot_type')->map(fn ($g) => $g->sum('total_ot_hours'));

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
            'company', 'employee', 'claims', 'totalHours', 'hoursByType', 'submitters', 'approvers', 'calendarEvents', 'from', 'to'
        ))->setPaper('a4', 'portrait');

        $name = str_replace(' ', '-', strtolower($employee->name));

        return $pdf->download("ot-claims-{$name}.pdf");
    }
}
