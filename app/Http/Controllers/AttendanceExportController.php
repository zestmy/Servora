<?php

namespace App\Http\Controllers;

use App\Livewire\Hr\AttendanceRecords;
use App\Models\AttendanceCode;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Outlet;
use App\Models\ServiceChargePeriod;
use App\Services\Hr\LatePenalties;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * PDF export of the Attendance Record grid. Honours the same query string
 * filters and outlet-access scoping as the Livewire grid, so what you see
 * is what you export.
 */
class AttendanceExportController extends Controller
{
    public function pdf(Request $request)
    {
        $data = $this->gather($request);

        $pdf = Pdf::loadView('pdf.attendance', $data)->setPaper('a4', 'landscape');

        return $pdf->stream('Attendance-' . $data['from']->format('Y-m-d') . '-to-' . $data['to']->format('Y-m-d') . '.pdf');
    }

    /**
     * One service charge payout slip per employee.
     *
     * Built from the SAME gather() the grid export uses, so a slip and the
     * table it came from cannot disagree about what someone is owed — which
     * is the only reason to hand one to a member of staff.
     */
    public function payout(Request $request)
    {
        $data = $this->gather($request, forceServiceCharge: true);

        abort_unless($data['canViewPay'], 403);
        abort_if($data['serviceCharge'] === null, 404,
            'No service charge has been saved for this period and outlet.');

        // Only people with a share get a slip: a page reading RM0.00 because
        // someone has no service points is not a payout, it is confusing.
        $rows = collect($data['serviceCharge']['rows'])
            ->filter(fn ($r) => $r['points'] > 0)
            ->values();

        abort_if($rows->isEmpty(), 404, 'Nobody in this view has service points for this period.');

        $pdf = Pdf::loadView('pdf.service-charge-payout', [
            'rows'          => $rows,
            'serviceCharge' => $data['serviceCharge'],
            'from'          => $data['from'],
            'to'            => $data['to'],
            'brandName'     => $data['brandName'],
            'logoBase64'    => $data['logoBase64'],
            'outletName'    => $data['outletName'],
            'lateRate'      => (float) \App\Models\ClockSetting::forCompany(Auth::user()->company_id)->late_rate_per_minute,
            'exportedBy'    => Auth::user()->name ?? Auth::user()->email,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('Service-Charge-Payout-' . $data['from']->format('Y-m-d') . '.pdf');
    }

    /**
     * Everything both exports need: the scoped employee list, the attendance
     * grid behind it, and the service charge distribution.
     *
     * @param  bool  $forceServiceCharge  the payout slips are ABOUT the pool,
     *         so they do not depend on the grid's service_charge=1 toggle.
     */
    private function gather(Request $request, bool $forceServiceCharge = false): array
    {
        $user = Auth::user();

        $accessible = $user->accessibleOutletIds();

        // Period — clamped to the grid's maximum like the Livewire component.
        try {
            $from = Carbon::parse((string) $request->input('from'))->startOfDay();
        } catch (\Throwable $e) {
            $from = now()->startOfMonth();
        }
        try {
            $to = Carbon::parse((string) $request->input('to'))->startOfDay();
        } catch (\Throwable $e) {
            $to = now()->endOfMonth()->startOfDay();
        }
        if ($to->lt($from)) $to = $from->copy();
        if ($from->diffInDays($to) >= AttendanceRecords::MAX_DAYS) {
            $to = $from->copy()->addDays(AttendanceRecords::MAX_DAYS - 1);
        }

        // Same rule as the grid: active staff plus anyone who resigned during
        // the exported period, so the PDF matches what was on screen.
        $query = Employee::with(['outlet', 'section'])
            ->whereIn('outlet_id', $accessible ?: [0])
            ->employedDuring($from->toDateString())
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('name');

        if (! Employee::canViewPay($user)) {
            $query->select(array_values(array_diff(
                Schema::getColumnListing('employees'),
                Employee::SENSITIVE_PAY_ATTRIBUTES
            )));
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $s = '%' . $search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhere('staff_id', 'like', $s)
                  ->orWhere('designation', 'like', $s);
            });
        }
        $outletFilter = (string) $request->input('outlet', '');
        if ($outletFilter !== '' && in_array((int) $outletFilter, $accessible, true)) {
            $query->where('outlet_id', (int) $outletFilter);
        }
        $sectionFilter = (string) $request->input('section', '');
        if ($sectionFilter !== '') {
            $query->where('section_id', (int) $sectionFilter);
        }
        $employmentLabel  = null;
        $employmentStatus = (string) $request->input('employment_status', '');
        if ($employmentStatus === 'none') {
            $query->whereNull('employment_status');
            $employmentLabel = 'No Employment Status';
        } elseif ($employmentStatus === 'exclude_outsourcing') {
            $query->where(function ($q) {
                $q->whereNull('employment_status')->orWhere('employment_status', '!=', 'outsourcing');
            });
            $employmentLabel = 'All Exclude Outsourcing';
        } elseif ($employmentStatus !== '' && isset(Employee::EMPLOYMENT_STATUSES[$employmentStatus])) {
            $query->where('employment_status', $employmentStatus);
            $employmentLabel = Employee::EMPLOYMENT_STATUSES[$employmentStatus];
        }

        $employees = $query->get();

        $codes     = AttendanceCode::orderBy('sort_order')->orderBy('code')->get();
        $codesById = $codes->keyBy('id');

        $cellMap = AttendanceRecord::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$from, $to])
            ->get()
            ->mapWithKeys(fn ($r) => [$r->employee_id . ':' . $r->work_date->format('Y-m-d') => $r->attendance_code_id]);

        $dates = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $dates[] = $d->copy();
        }

        // Legend shows active codes plus any inactive ones still present in
        // the exported range, so every cell on the page is explained.
        $usedCodeIds = collect($cellMap)->unique()->all();
        $legendCodes = $codes->filter(fn ($c) => $c->is_active || in_array($c->id, $usedCodeIds, true))->values();

        $company    = $user->company;
        $brandName  = $company?->brand_name ?: $company?->name;
        $logoBase64 = $this->companyLogoBase64($company);

        $outletName = $outletFilter !== '' ? Outlet::find((int) $outletFilter)?->name : null;

        // Optional Service Charge section: included when the grid's panel is
        // open (service_charge=1) AND a pool has been saved for this exact
        // period + outlet selection (same key as the Livewire panel).
        // Service points and the distribution are pay data — hr.compensation
        // only, matching the Livewire grid.
        $canViewPay = Employee::canViewPay($user);

        $serviceCharge = null;
        if ($canViewPay && ($forceServiceCharge || $request->boolean('service_charge'))) {
            $scOutletId = ($outletFilter !== '' && in_array((int) $outletFilter, $accessible, true))
                ? (int) $outletFilter : null;
            $scRow = ServiceChargePeriod::where('outlet_id', $scOutletId)
                ->whereDate('period_from', $from)
                ->whereDate('period_to', $to)
                ->first();
            if ($scRow) {
                // RM/point base: ALL active employees in the outlet scope —
                // section/employment/search only narrow the displayed rows.
                $totalPoints = (float) Employee::whereIn('outlet_id', $accessible ?: [0])
                    ->where('is_active', true)
                    ->when($scOutletId !== null, fn ($q) => $q->where('outlet_id', $scOutletId))
                    ->sum('service_points_entitlement');
                $latePenalties = LatePenalties::forPeriod($user->company_id, $scOutletId, $from, $to);
                $serviceCharge = ServiceChargePeriod::distribute($scRow, $employees, $codes, $cellMap, 5.0, 10.0, $totalPoints, $latePenalties);
            }
        }

        return compact(
            'employees', 'dates', 'from', 'to', 'codesById', 'cellMap',
            'legendCodes', 'brandName', 'logoBase64', 'outletName', 'employmentLabel',
            'serviceCharge', 'canViewPay'
        );
    }

    private function companyLogoBase64($company): ?string
    {
        if (! $company?->logo) return null;
        try {
            $path = Storage::disk('public')->path($company->logo);
            if (file_exists($path)) {
                $mime = mime_content_type($path);
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
}
