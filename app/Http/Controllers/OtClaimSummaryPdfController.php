<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\OvertimeClaim;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OtClaimSummaryPdfController extends Controller
{
    public function __invoke(Request $request)
    {
        $user    = $request->user();
        $company = Company::find($user->company_id);

        // Use same outlet scope as the Livewire component — cross-outlet roles
        // see all company outlets; everyone else sees only assigned outlets.
        $availableOutletIds = $user->accessibleOutletIds();

        // If outlet filter is specified, narrow down to that outlet only
        $outletFilter = $request->input('outlet');
        if ($outletFilter && in_array((int) $outletFilter, $availableOutletIds)) {
            $availableOutletIds = [(int) $outletFilter];
        }

        // Any date range. month/year is still honoured so links saved from the
        // old month-only modal keep working.
        [$from, $to] = $this->resolvePeriod($request);

        $otTypeLabels = [
            'normal_day'     => 'Normal Day',
            'rest_day'       => 'Rest Day',
            'public_holiday' => 'Public Holiday',
        ];

        // Fetch all approved claims for the period - filter by EMPLOYEE's outlet, not claim's outlet
        // This ensures claims appear in the correct outlet's report even if claim.outlet_id was set incorrectly
        $claims = OvertimeClaim::with('employee')
            ->join('employees', 'overtime_claims.employee_id', '=', 'employees.id')
            ->whereIn('employees.outlet_id', $availableOutletIds)
            ->where('overtime_claims.status', 'approved')
            ->whereBetween('overtime_claims.claim_date', [$from, $to])
            ->select('overtime_claims.*')
            ->get();

        // Build per-employee summary, sorted by employee name
        // rows = [ { employee, byType: [{ ot_type, label, hours }], totalHours } ]
        $rows = $claims
            ->groupBy('employee_id')
            ->map(function ($empClaims) use ($otTypeLabels) {
                $employee = $empClaims->first()->employee;

                $byType = collect($otTypeLabels)
                    ->map(fn ($label, $key) => [
                        'ot_type' => $key,
                        'label'   => $label,
                        'hours'   => (float) $empClaims->where('ot_type', $key)->sum('total_ot_hours'),
                    ])
                    ->filter(fn ($t) => $t['hours'] > 0)
                    ->values();

                return [
                    'employee'   => $employee,
                    'byType'     => $byType,
                    'totalHours' => (float) $empClaims->sum('total_ot_hours'),
                ];
            })
            ->sortBy(fn ($r) => $r['employee']?->name)
            ->values();

        $grandTotalHours = (float) $claims->sum('total_ot_hours');

        // Hours still pending approval in this period/scope — excluded from this
        // approved-only report, noted in the footer so the total isn't confusing.
        $pendingHours = (float) OvertimeClaim::query()
            ->join('employees', 'overtime_claims.employee_id', '=', 'employees.id')
            ->whereIn('employees.outlet_id', $availableOutletIds)
            ->where('overtime_claims.status', 'submitted')
            ->whereBetween('overtime_claims.claim_date', [$from, $to])
            ->sum('overtime_claims.total_ot_hours');

        // Rejected claims in this period/scope — listed below the report with
        // the rejector and their reason for the record.
        $rejectedClaims = OvertimeClaim::with(['employee', 'approver'])
            ->join('employees', 'overtime_claims.employee_id', '=', 'employees.id')
            ->whereIn('employees.outlet_id', $availableOutletIds)
            ->where('overtime_claims.status', 'rejected')
            ->whereBetween('overtime_claims.claim_date', [$from, $to])
            ->select('overtime_claims.*')
            ->orderBy('employees.name')
            ->orderBy('overtime_claims.claim_date')
            ->get();

        // OT-type column totals for footer
        $typeTotals = [];
        foreach ($otTypeLabels as $key => $label) {
            $typeTotals[$key] = (float) $claims->where('ot_type', $key)->sum('total_ot_hours');
        }

        $periodLabel = $this->periodLabel($from, $to);
        $exportedBy  = $user->name ?? $user->email;

        $pdf = Pdf::loadView('pdf.ot-claims-summary', compact(
            'company', 'rows', 'otTypeLabels', 'typeTotals',
            'grandTotalHours', 'periodLabel', 'from', 'to', 'exportedBy', 'pendingHours', 'rejectedClaims'
        ))->setPaper('a4', 'portrait');

        return $pdf->download("ot-claims-summary-{$from}-to-{$to}.pdf");
    }

    /**
     * The report period as [from, to] Y-m-d strings.
     *
     * Prefers an explicit from/to range; falls back to month + year (the old
     * modal's only option, still live in bookmarked links), then to the current
     * month. A reversed range is swapped rather than returning nothing.
     */
    private function resolvePeriod(Request $request): array
    {
        $parse = function (?string $value): ?string {
            if (! $value) return null;
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Exception) {
                return null;
            }
        };

        $from = $parse($request->input('from'));
        $to   = $parse($request->input('to'));

        if (! $from || ! $to) {
            $month = (int) $request->input('month', now()->month);
            $year  = (int) $request->input('year',  now()->year);
            $month = min(12, max(1, $month));

            $start = Carbon::create($year, $month, 1);
            $from ??= $start->toDateString();
            $to   ??= $start->copy()->endOfMonth()->toDateString();
        }

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    /** "August 2026" for a whole month, "2026" for a whole year, else a range. */
    private function periodLabel(string $from, string $to): string
    {
        $start = Carbon::parse($from);
        $end   = Carbon::parse($to);

        if ($start->isSameDay($end)) {
            return $start->format('d M Y');
        }
        if ($start->equalTo($start->copy()->startOfMonth()) && $end->equalTo($start->copy()->endOfMonth())) {
            return $start->format('F Y');
        }
        if ($start->equalTo($start->copy()->startOfYear()) && $end->equalTo($start->copy()->endOfYear())) {
            return $start->format('Y');
        }

        return $start->format('d M Y') . ' — ' . $end->format('d M Y');
    }
}
